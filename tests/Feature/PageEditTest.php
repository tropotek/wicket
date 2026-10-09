<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageEditTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'My New Page',
            'slug' => '',
            'category' => 'Notes',
            'visibility' => 'members',
            'publish' => '1',
            'title_visible' => '1',
            'html' => '<p>Body</p>',
            'description' => 'Desc',
            'keywords' => 'a, b',
        ], $overrides);
    }

    private function pageWithContent(array $attrs = [], ?User $author = null): Page
    {
        $page = Page::factory()->for($author ?? User::factory()->editor()->create(), 'author')
            ->create(['slug' => 'existing', 'title' => 'Existing'] + $attrs);
        Content::factory()->for($page)->create([
            'user_id' => $page->author_id, 'html' => '<p>Old</p>', 'description' => '', 'keywords' => '',
        ]);

        return $page;
    }

    private function updatePayload(Page $page, array $overrides = []): array
    {
        $content = $page->latestContent;

        return $this->payload(array_merge([
            'title' => $page->title,
            'slug' => $page->slug,
            'category' => $page->category,
            'visibility' => $page->visibility->value,
            'html' => $content->html,
            'description' => $content->description,
            'keywords' => $content->keywords,
        ], $overrides));
    }

    public function test_create_form_access(): void
    {
        $this->get('/pages/create')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/pages/create')->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())->get('/pages/create')
            ->assertOk()->assertSee('name="html"', false);
    }

    public function test_store_creates_page_and_first_revision(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post('/pages', $this->payload())
            ->assertRedirect('/my-new-page');

        $page = Page::where('slug', 'my-new-page')->firstOrFail();
        $this->assertSame($editor->id, $page->author_id);
        $this->assertSame(Visibility::Members, $page->visibility);
        $this->assertSame('Notes', $page->category);
        $this->assertCount(1, $page->contents);
        $this->assertSame('<p>Body</p>', $page->latestContent->html);
        $this->assertSame($editor->id, $page->latestContent->user_id);
    }

    public function test_members_cannot_store(): void
    {
        $this->actingAs(User::factory()->create())->post('/pages', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('pages', 0);
    }

    public function test_blank_slug_is_derived_and_deduplicated(): void
    {
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post('/pages', $this->payload());
        $this->actingAs($editor)->post('/pages', $this->payload());
        $this->actingAs($editor)->post('/pages', $this->payload());

        $this->assertEqualsCanonicalizing(
            ['my-new-page', 'my-new-page-2', 'my-new-page-3'],
            Page::pluck('slug')->all(),
        );
    }

    public function test_explicit_slug_is_used(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->post('/pages', $this->payload(['slug' => 'custom-slug']))
            ->assertRedirect('/custom-slug');
    }

    public function test_bad_slugs_are_rejected(): void
    {
        $editor = User::factory()->editor()->create();
        Page::factory()->create(['slug' => 'taken']);

        foreach (['Has Spaces', 'UPPER', '-leading', 'trailing-', 'a--b', 'taken', 'login', 'admin', 'pages', 'up', 'build'] as $slug) {
            $this->actingAs($editor)->post('/pages', $this->payload(['slug' => $slug]))
                ->assertSessionHasErrors('slug');
        }

        $this->assertDatabaseCount('pages', 1);
    }

    public function test_title_that_slugs_to_a_reserved_word_gets_a_suffix(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->post('/pages', $this->payload(['title' => 'Login']))
            ->assertRedirect('/login-2');
    }

    public function test_edit_form_access(): void
    {
        $author = User::factory()->editor()->create();
        $page = $this->pageWithContent(['visibility' => Visibility::Private], $author);

        $this->actingAs(User::factory()->create())->get('/pages/existing/edit')->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())->get('/pages/existing/edit')->assertForbidden();
        $this->actingAs($author)->get('/pages/existing/edit')->assertOk()->assertSee('&lt;p&gt;Old&lt;/p&gt;', false);
    }

    public function test_update_appends_a_revision_and_updates_metadata(): void
    {
        $page = $this->pageWithContent(['visibility' => Visibility::Public]);
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->put('/pages/existing', $this->updatePayload($page, [
            'title' => 'Renamed Title',
            'category' => 'Guides',
            'html' => '<p>New</p>',
        ]))->assertSessionHasNoErrors()->assertRedirect('/existing');

        $page->refresh();
        $this->assertSame('Renamed Title', $page->title);
        $this->assertSame('existing', $page->slug, 'slug does not follow the title on edit');
        $this->assertSame('Guides', $page->category);
        $this->assertCount(2, $page->contents);
        $this->assertSame('<p>New</p>', $page->latestContent->html);
        $this->assertSame($editor->id, $page->latestContent->user_id);
        $this->assertNotSame($editor->id, $page->author_id);
    }

    public function test_metadata_only_save_does_not_add_a_revision(): void
    {
        $page = $this->pageWithContent(['visibility' => Visibility::Public]);

        $this->actingAs(User::factory()->editor()->create())
            ->put('/pages/existing', $this->updatePayload($page, ['title' => 'Only title changed']))
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $page->fresh()->contents);
        $this->assertSame('Only title changed', $page->fresh()->title);
    }

    public function test_renaming_slug_redirects_to_new_url(): void
    {
        $page = $this->pageWithContent(['visibility' => Visibility::Public]);

        $this->actingAs(User::factory()->editor()->create())
            ->put('/pages/existing', $this->updatePayload($page, ['slug' => 'moved']))
            ->assertRedirect('/moved');

        $this->get('/moved')->assertOk();
    }

    public function test_update_keeps_own_slug_without_unique_error(): void
    {
        $page = $this->pageWithContent(['visibility' => Visibility::Public]);

        $this->actingAs(User::factory()->editor()->create())
            ->put('/pages/existing', $this->updatePayload($page))
            ->assertSessionHasNoErrors();
    }

    public function test_protected_fields_are_ignored(): void
    {
        $page = $this->pageWithContent(['visibility' => Visibility::Public]);
        $authorId = $page->author_id;

        $this->actingAs(User::factory()->editor()->create())
            ->put('/pages/existing', $this->updatePayload($page, ['author_id' => 999, 'views' => 999]));

        $this->assertSame($authorId, $page->fresh()->author_id);
        $this->assertSame(0, $page->fresh()->views);
    }

    public function test_links_by_role(): void
    {
        $this->pageWithContent(['visibility' => Visibility::Public]);

        $this->actingAs(User::factory()->create())->get('/existing')
            ->assertDontSee('href="'.route('pages.edit', 'existing').'"', false)
            ->assertDontSee('href="'.route('pages.create').'"', false);

        $this->actingAs(User::factory()->editor()->create())->get('/existing')
            ->assertSee('href="'.route('pages.edit', 'existing').'"', false)
            ->assertSee('href="'.route('pages.create').'"', false);
    }
}

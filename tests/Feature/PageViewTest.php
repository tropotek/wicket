<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageViewTest extends TestCase
{
    use RefreshDatabase;

    private function page(Visibility $visibility = Visibility::Public, array $attrs = [], ?User $author = null): Page
    {
        $page = Page::factory()
            ->for($author ?? User::factory()->editor()->create(), 'author')
            ->create(['visibility' => $visibility, 'slug' => 'test-page', 'title' => 'Test Page'] + $attrs);
        Content::factory()->for($page)->create([
            'user_id' => $page->author_id,
            'html' => '<p>Hello wicket</p>',
            'description' => 'A page about tests',
        ]);

        return $page;
    }

    public function test_public_page_renders_for_anyone(): void
    {
        $this->page();

        $this->get('/test-page')
            ->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('Test Page')
            ->assertSee('<p>Hello wicket</p>', false);
    }

    public function test_missing_slug_is_404(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_members_page_access(): void
    {
        $this->page(Visibility::Members);

        $this->get('/test-page')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->guest()->create())->get('/test-page')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/test-page')->assertOk();
    }

    public function test_login_redirect_returns_to_the_page(): void
    {
        $this->page(Visibility::Members);
        $user = User::factory()->create();

        $this->get('/test-page');
        $this->post('/login', ['login' => $user->username, 'password' => 'password'])
            ->assertRedirect(url('/test-page'));
    }

    public function test_private_page_access(): void
    {
        $author = User::factory()->editor()->create();
        $this->page(Visibility::Private, author: $author);

        $this->actingAs(User::factory()->editor()->create())->get('/test-page')->assertForbidden();
        $this->actingAs($author)->get('/test-page')->assertOk();
    }

    public function test_view_increments_counter_without_touching_updated_at(): void
    {
        $page = $this->page();
        $page->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $before = $page->fresh()->updated_at;

        $this->withoutDefer();
        $this->get('/test-page');
        $this->get('/test-page');

        $page->refresh();
        $this->assertSame(2, $page->views);
        $this->assertEquals($before, $page->updated_at);
    }

    public function test_forbidden_view_does_not_count(): void
    {
        $page = $this->page(Visibility::Private);

        $this->withoutDefer();
        $this->get('/test-page');

        $this->assertSame(0, $page->fresh()->views);
    }

    public function test_hidden_title(): void
    {
        $this->page(attrs: ['title_visible' => false]);

        $this->get('/test-page')->assertOk()->assertDontSee('<h1', false);
    }

    public function test_meta_description_in_head(): void
    {
        $this->page();

        $this->get('/test-page')->assertSee('<meta name="description" content="A page about tests">', false);
    }

    public function test_unpublished_badge_only_for_editors(): void
    {
        $this->page(attrs: ['publish' => false]);

        $this->get('/test-page')->assertOk()->assertDontSee('Unpublished');
        $this->actingAs(User::factory()->editor()->create())->get('/test-page')->assertSee('Unpublished');
    }

    public function test_fixed_routes_win_over_page_slugs(): void
    {
        $this->page(attrs: ['slug' => 'login']);

        $this->get('/login')->assertOk()->assertSee('name="login"', false);
        $this->get('/up')->assertOk()->assertJsonStructure(['version']);
    }
}

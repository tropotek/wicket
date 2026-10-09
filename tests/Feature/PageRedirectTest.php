<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug = 'old-slug'): Page
    {
        return Page::factory()
            ->for(User::factory()->editor()->create(), 'author')
            ->create(['slug' => $slug, 'visibility' => Visibility::Public]);
    }

    private function rename(Page $page, string $slug): void
    {
        $page->slug = $slug;
        $page->save();
    }

    public function test_renaming_records_the_old_slug(): void
    {
        $page = $this->page();

        $this->rename($page, 'new-slug');

        $this->assertDatabaseHas('page_redirects', ['slug' => 'old-slug', 'page_id' => $page->id]);
    }

    public function test_saves_without_a_slug_change_write_nothing(): void
    {
        $page = $this->page();

        $page->update(['title' => 'Another title']);

        $this->assertSame(0, PageRedirect::count());
    }

    public function test_old_slug_301s_to_the_current_slug_keeping_the_query(): void
    {
        $page = $this->page();
        $this->rename($page, 'new-slug');

        $this->get('/old-slug?a=1&b=2')
            ->assertStatus(301)
            ->assertRedirect('/new-slug?a=1&b=2');
    }

    public function test_every_old_slug_goes_straight_to_the_current_one(): void
    {
        $page = $this->page('a');
        $this->rename($page, 'b');
        $this->rename($page, 'c');

        $this->get('/a')->assertStatus(301)->assertRedirect('/c');
        $this->get('/b')->assertStatus(301)->assertRedirect('/c');
    }

    public function test_renaming_back_to_an_old_slug_clears_its_redirect(): void
    {
        $page = $this->page('a');
        $this->rename($page, 'b');
        $this->rename($page, 'a');

        $this->assertDatabaseMissing('page_redirects', ['slug' => 'a']);
        $this->assertDatabaseHas('page_redirects', ['slug' => 'b', 'page_id' => $page->id]);
        $this->get('/a')->assertOk();
        $this->get('/b')->assertStatus(301)->assertRedirect('/a');
    }

    public function test_a_new_page_claiming_an_old_slug_wins(): void
    {
        $first = $this->page();
        $this->rename($first, 'new-slug');

        $second = $this->page('old-slug');

        $this->assertDatabaseMissing('page_redirects', ['slug' => 'old-slug']);
        $this->get('/old-slug')->assertOk()->assertSee($second->title);
    }

    public function test_old_slug_of_a_trashed_page_is_404(): void
    {
        $page = $this->page();
        $this->rename($page, 'new-slug');

        $page->delete();

        $this->get('/old-slug')->assertNotFound();
    }

    public function test_purging_a_page_drops_its_redirects(): void
    {
        $page = $this->page();
        $this->rename($page, 'new-slug');

        $page->forceDelete();

        $this->assertSame(0, PageRedirect::count());
    }

    public function test_unknown_slug_is_still_404(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_renaming_through_the_edit_form_redirects_the_old_slug(): void
    {
        $page = $this->page();
        $page->contents()->create(['html' => '<p>x</p>']);

        $this->actingAs($page->author)->put(route('pages.update', $page), [
            'title' => $page->title,
            'slug' => 'new-slug',
            'visibility' => Visibility::Public->value,
            'html' => '<p>x</p>',
        ])->assertRedirect('/new-slug');

        $this->get('/old-slug')->assertStatus(301)->assertRedirect('/new-slug');
    }
}

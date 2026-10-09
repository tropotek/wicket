<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Page;
use App\Models\PageLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkIndexTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, ?string $html = null): Page
    {
        $page = Page::factory()->create(['slug' => $slug, 'title' => ucfirst($slug), 'visibility' => Visibility::Public]);
        if ($html !== null) {
            $page->contents()->create(['html' => $html]);
        }

        return $page;
    }

    private function linksOf(Page $page): array
    {
        return PageLink::where('page_id', $page->id)->orderBy('slug')->pluck('slug')->all();
    }

    private function broken(): array
    {
        return PageLink::broken()->orderBy('slug')->get()->map(fn ($l) => $l->page_id.':'.$l->slug)->all();
    }

    public function test_new_revisions_reindex_links(): void
    {
        $page = $this->page('a', '<a href="/b">b</a> <a href="/c">c</a> <a href="/a">self</a> <a href="https://x.org/d">ext</a>');
        $this->assertSame(['b', 'c'], $this->linksOf($page));

        $page->contents()->create(['html' => '<a href="/d">d</a>']);
        $this->assertSame(['d'], $this->linksOf($page));

        $page->contents()->create(['html' => '<p>no links</p>']);
        $this->assertSame([], $this->linksOf($page));
    }

    public function test_saving_and_restoring_through_the_app_reindexes(): void
    {
        $editor = User::factory()->editor()->create();
        $page = Page::factory()->for($editor, 'author')->create(['slug' => 'doc', 'visibility' => Visibility::Public]);
        $form = ['title' => 'Doc', 'slug' => 'doc', 'visibility' => 'public', 'publish' => '1'];

        $this->actingAs($editor)->put(route('pages.update', $page), $form + ['html' => '<a href="/first">1</a>']);
        $first = $page->latestContent()->first();
        $this->actingAs($editor)->put(route('pages.update', $page), $form + ['html' => '<a href="/second">2</a>']);
        $this->assertSame(['second'], $this->linksOf($page));

        $this->actingAs($editor)->post(route('pages.restore', [$page, $first]));
        $this->assertSame(['first'], $this->linksOf($page));
    }

    public function test_broken_follows_targets_as_they_change(): void
    {
        $source = $this->page('source', '<a href="/target">t</a> <a href="/other">o</a>');
        $this->page('other');
        $this->assertSame(["{$source->id}:target"], $this->broken());

        $target = $this->page('target');
        $this->assertSame([], $this->broken());

        $target->update(['slug' => 'renamed']);
        $this->assertSame([], $this->broken(), 'old slug still reaches the page via its redirect');

        $target->delete();
        $this->assertSame(["{$source->id}:target"], $this->broken());

        $source->delete();
        $this->assertSame([], $this->broken(), 'links from trashed pages are not reported');
    }

    public function test_purging_a_page_drops_its_links(): void
    {
        $page = $this->page('a', '<a href="/b">b</a>');
        $page->forceDelete();

        $this->assertSame(0, PageLink::count());
    }

    public function test_rebuild_command(): void
    {
        $page = $this->page('a', '<a href="/b">b</a>');
        PageLink::query()->delete();

        $this->artisan('wicket:links')->expectsOutputToContain('Indexed links for 1 pages.')->assertSuccessful();
        $this->assertSame(['b'], $this->linksOf($page));
    }

    public function test_admin_broken_links_page(): void
    {
        $this->page('source', '<a href="/missing-one">m</a> <a href="/fine">f</a>');
        $this->page('fine');

        $this->actingAs(User::factory()->editor()->create())->get('/admin/links')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/links')
            ->assertOk()
            ->assertSee(['Source', '/missing-one'])
            ->assertDontSee('/fine</a>', false)
            ->assertSee(route('pages.create', ['title' => 'Missing one', 'slug' => 'missing-one']));
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSee('Broken links');
    }
}

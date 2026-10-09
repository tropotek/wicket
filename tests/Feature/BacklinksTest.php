<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Facades\Settings;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BacklinksTest extends TestCase
{
    use RefreshDatabase;

    private Page $target;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->target = $this->page('Target', 'target');
    }

    private function page(string $title, string $slug, string $html = '<p>x</p>', array $attrs = []): Page
    {
        $page = Page::factory()->create($attrs + ['title' => $title, 'slug' => $slug, 'visibility' => Visibility::Public]);
        $page->contents()->create(['html' => $html]);

        return $page;
    }

    private function titles(?User $viewer = null): array
    {
        return $this->target->backlinks($viewer)->pluck('title')->all();
    }

    public function test_pages_linking_by_current_or_old_slug(): void
    {
        $this->page('Alpha', 'alpha', '<a href="/target">t</a>');
        $this->target->update(['slug' => 'renamed']);
        $this->page('Beta', 'beta', '<a href="/renamed">t</a>');
        $this->page('Gamma', 'gamma', '<a href="/unrelated">u</a>');

        $this->assertSame(['Alpha', 'Beta'], $this->titles());
    }

    public function test_self_trashed_and_hidden_linkers_are_left_out(): void
    {
        $this->target->contents()->create(['html' => '<a href="/target">me</a>']);
        $this->page('Trashed', 'trashed', '<a href="/target">t</a>')->delete();
        $author = User::factory()->editor()->create();
        $this->page('Secret Plans', 'plans', '<a href="/target">t</a>', ['visibility' => Visibility::Private, 'author_id' => $author->id]);

        $this->assertSame([], $this->titles());
        $this->assertSame(['Secret Plans'], $this->titles($author));
    }

    public function test_block_on_the_page_view(): void
    {
        $this->get('/target')->assertOk()->assertDontSee('Linked from');

        $this->page('Alpha', 'alpha', '<a href="/target">t</a>');
        $this->get('/target')->assertSee('Linked from 1 page:')->assertSee(route('pages.show', 'alpha'));
    }

    public function test_long_lists_are_cut_with_a_count(): void
    {
        foreach (range(1, 23) as $i) {
            $this->page(sprintf('Linker %02d', $i), "linker-{$i}", '<a href="/target">t</a>');
        }

        $this->get('/target')->assertSee('Linked from 23 pages:')->assertSee('Linker 20')->assertDontSee('Linker 21')->assertSee('and 3 more');
    }

    public function test_switch_off_hides_the_block(): void
    {
        $this->page('Alpha', 'alpha', '<a href="/target">t</a>');
        Settings::set(['wiki.backlinks' => false]);

        $this->get('/target')->assertDontSee('Linked from');
    }

    public function test_backlinks_cost_one_query(): void
    {
        $this->page('Alpha', 'alpha', '<a href="/target">t</a>');

        DB::enableQueryLog();
        $this->target->backlinks(null)->get();

        $this->assertCount(1, DB::getQueryLog());
    }
}

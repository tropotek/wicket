<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Html\Passes\InternalLinks;
use App\Html\Pipeline;
use App\Html\RenderContext;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalLinksTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $html, ?User $viewer = null): string
    {
        return (new Pipeline($this->app, [InternalLinks::class]))->render($html, new RenderContext(null, $viewer));
    }

    public function test_live_page_link(): void
    {
        Page::factory()->create(['slug' => 'faq', 'visibility' => Visibility::Public]);

        $this->assertSame('<a href="/faq" class="wk-page">FAQ</a>', $this->render('<a href="/faq">FAQ</a>'));
    }

    public function test_missing_and_trashed_pages_are_new(): void
    {
        $trashed = Page::factory()->create(['slug' => 'gone']);
        $trashed->delete();

        foreach (['missing', 'gone'] as $slug) {
            $this->assertSame(
                '<a href="/'.$slug.'" class="wk-page-new" title="This page doesn\'t exist yet">x</a>',
                $this->render('<a href="/'.$slug.'" class="wk-page">x</a>'),
            );
        }
    }

    public function test_old_slug_is_rewritten_to_the_current_one_keeping_query_and_fragment(): void
    {
        $page = Page::factory()->create(['slug' => 'old-name', 'visibility' => Visibility::Public]);
        $page->update(['slug' => 'new-name']);

        $this->assertSame('<a href="/new-name?a=1#part" class="wk-page">x</a>', $this->render('<a href="/old-name?a=1#part">x</a>'));
    }

    public function test_pages_the_viewer_cannot_open_are_locked_but_guests_keep_the_link(): void
    {
        Page::factory()->create(['slug' => 'staff', 'visibility' => Visibility::Editors]);
        $html = '<a href="/staff" class="wk-page">x</a>';

        $this->assertStringContainsString('wk-page-locked', $this->render($html, User::factory()->create()));
        $this->assertStringNotContainsString('wk-page-locked', $this->render($html, User::factory()->editor()->create()));
        $this->assertSame($html, $this->render($html));
    }

    public function test_other_links_are_untouched(): void
    {
        $html = '<a href="/data/media/x.pdf">file</a><a href="/login">in</a><a href="https://example.org/faq">ext</a>';

        $this->assertSame($html, $this->render($html));
    }

    public function test_page_view_marks_links(): void
    {
        $page = Page::factory()->create(['slug' => 'home', 'visibility' => Visibility::Public]);
        $page->contents()->create(['html' => '<p><a href="/home">self</a> <a href="/nope">nope</a></p>']);

        $this->get('/home')->assertOk()
            ->assertSee('<a href="/home" class="wk-page">self</a>', false)
            ->assertSee('class="wk-page-new"', false);
    }
}

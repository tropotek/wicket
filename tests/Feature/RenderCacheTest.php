<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Html\HtmlPass;
use App\Html\Passes\InternalLinks;
use App\Html\RenderContext;
use App\Models\Page;
use App\Models\User;
use Dom\HTMLDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderCacheTest extends TestCase
{
    use RefreshDatabase;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'wicket.render_cache' => true]);
        config(['wicket.html_passes' => [InternalLinks::class, CountRenders::class]]);
        CountRenders::$count = 0;

        $this->page = Page::factory()->create(['slug' => 'home', 'visibility' => Visibility::Public]);
        $this->page->contents()->create(['html' => '<p>See <a href="/target">target</a></p>']);
    }

    public function test_guest_views_reuse_the_render(): void
    {
        $this->get('/home')->assertOk();
        $this->get('/home')->assertOk();

        $this->assertSame(1, CountRenders::$count);
    }

    public function test_signed_in_views_always_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/home');
        $this->actingAs($user)->get('/home');

        $this->assertSame(2, CountRenders::$count);
    }

    public function test_writes_retire_cached_renders(): void
    {
        $this->get('/home')->assertSee('wk-page-new', false);

        $target = Page::factory()->create(['slug' => 'target', 'visibility' => Visibility::Public]);
        $this->get('/home')->assertSee('class="wk-page"', false)->assertDontSee('wk-page-new', false);

        $target->update(['slug' => 'moved']);
        $this->get('/home')->assertSee('href="/moved"', false);

        $target->delete();
        $this->get('/home')->assertSee('wk-page-new', false);

        $this->page->contents()->create(['html' => '<p>Fresh words</p>']);
        $this->get('/home')->assertSee('Fresh words');
    }

    public function test_can_be_switched_off(): void
    {
        config(['wicket.render_cache' => false]);

        $this->get('/home');
        $this->get('/home');

        $this->assertSame(2, CountRenders::$count);
    }

    public function test_host_is_part_of_the_key(): void
    {
        $this->get('http://one.test/home');
        $this->get('http://two.test/home');

        $this->assertSame(2, CountRenders::$count);
    }
}

class CountRenders implements HtmlPass
{
    public static int $count = 0;

    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        self::$count++;
    }
}

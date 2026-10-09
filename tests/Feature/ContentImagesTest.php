<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Html\Passes\ContentImages;
use App\Html\Pipeline;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentImagesTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $html): string
    {
        return (new Pipeline($this->app, [ContentImages::class]))->render($html);
    }

    public function test_unlinked_images_are_wrapped_for_the_lightbox(): void
    {
        $this->assertSame(
            '<p><a href="/data/media/a.png" class="wk-lightbox"><img src="/data/media/a.png" alt="A" class="wk-image" loading="lazy"></a></p>',
            $this->render('<p><img src="/data/media/a.png" alt="A"></p>'),
        );
    }

    public function test_linked_and_opted_out_images_are_not_wrapped(): void
    {
        $this->assertSame(
            '<a href="/faq"><img src="/x.png" class="wk-image" loading="lazy"></a>',
            $this->render('<a href="/faq"><img src="/x.png"></a>'),
        );
        $this->assertSame(
            '<img src="/x.png" class="no-lightbox wk-image" loading="eager">',
            $this->render('<img src="/x.png" class="no-lightbox" loading="eager">'),
        );
    }

    public function test_page_view_loads_prism_only_for_code(): void
    {
        $page = Page::factory()->create(['slug' => 'code', 'visibility' => Visibility::Public]);
        $page->contents()->create(['html' => '<pre class="language-php"><code>&lt;?php echo 1;</code></pre>']);
        $plain = Page::factory()->create(['slug' => 'plain', 'visibility' => Visibility::Public]);
        $plain->contents()->create(['html' => '<p>No code. <img src="/x.png"></p>']);

        $this->get('/code')->assertOk()->assertSee('resources/js/prism.js', false);
        $this->get('/plain')->assertOk()
            ->assertDontSee('resources/js/prism.js', false)
            ->assertSee('x-data="lightbox"', false)
            ->assertSee('class="wk-lightbox"', false);
    }
}

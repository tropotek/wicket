<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Html\Passes\ExternalLinks;
use App\Html\Pipeline;
use App\Models\Content;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExternalLinksTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $html): string
    {
        return (new Pipeline($this->app, [ExternalLinks::class]))->render($html);
    }

    public function test_external_links_open_in_a_new_tab(): void
    {
        $this->assertSame(
            '<a href="https://example.org/x" class="wk-link-external" target="_blank" rel="noopener noreferrer">x</a>',
            $this->render('<a href="https://example.org/x">x</a>'),
        );
    }

    public function test_own_site_relative_and_other_schemes_are_left_alone(): void
    {
        $own = 'http://'.request()->getHost().'/page';

        foreach ([
            '<a href="'.$own.'">own</a>',
            '<a href="'.str_replace('://', '://www.', $own).'">www own</a>',
            '<a href="/members-page" class="wk-page">rel</a>',
            '<a href="mailto:a@example.org">mail</a>',
            '<a href="#top">anchor</a>',
            '<a name="x">no href</a>',
        ] as $html) {
            $this->assertSame($html, $this->render($html), $html);
        }
    }

    public function test_image_links_open_in_a_new_tab_without_the_icon(): void
    {
        $this->assertSame(
            '<a href="https://example.org/x" target="_blank" rel="noopener noreferrer"><img src="/data/media/x.png"></a>',
            $this->render('<a href="https://example.org/x"><img src="/data/media/x.png"></a>'),
        );
    }

    public function test_existing_classes_are_kept(): void
    {
        $this->assertStringContainsString('class="fw-bold wk-link-external"', $this->render('<a class="fw-bold" href="https://example.org">x</a>'));
    }

    public function test_button_links_open_in_a_new_tab_without_the_icon(): void
    {
        $this->assertSame(
            '<a href="https://example.org/x" class="btn btn-primary" target="_blank" rel="noopener noreferrer">x</a>',
            $this->render('<a href="https://example.org/x" class="btn btn-primary">x</a>'),
        );
    }

    public function test_page_view_runs_the_pipeline(): void
    {
        $page = Page::factory()->create(['slug' => 'links', 'visibility' => Visibility::Public]);
        Content::factory()->for($page)->create(['html' => '<p><a href="https://example.org">out</a></p>']);

        $this->get('/links')->assertOk()->assertSee('class="wk-link-external" target="_blank" rel="noopener noreferrer"', false);
    }
}

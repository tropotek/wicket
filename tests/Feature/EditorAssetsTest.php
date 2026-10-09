<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_form_mounts_the_editor(): void
    {
        $editor = User::factory()->editor()->create();
        $page = Page::factory()->for($editor, 'author')->withContent()->create();

        foreach ([route('pages.create'), route('pages.edit', $page)] as $url) {
            $this->actingAs($editor)->get($url)
                ->assertOk()
                ->assertSee('resources/js/editor.js', false)
                ->assertSee('data-editor', false)
                ->assertSee('data-content-css="', false)
                ->assertSee('data-media-url="'.route('media.index').'"', false);
        }
    }

    public function test_other_pages_do_not_load_the_editor(): void
    {
        $page = Page::factory()->withContent()->create();

        $this->get(route('pages.show', $page))->assertOk()->assertDontSee('resources/js/editor.js', false);
        $this->get('/')->assertOk()->assertDontSee('resources/js/editor.js', false);
    }
}

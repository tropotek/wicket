<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagePickerTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->editor()->create();
    }

    private function page(string $title, array $attrs = []): Page
    {
        return Page::factory()->create($attrs + ['title' => $title, 'visibility' => Visibility::Public]);
    }

    public function test_access(): void
    {
        $this->get('/pages/picker')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/pages/picker')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/pages/picker/slug?title=x')->assertForbidden();
        $this->actingAs($this->editor)->get('/pages/picker')->assertOk();
    }

    public function test_lists_only_pages_the_user_can_see(): void
    {
        $this->page('Shared Notes');
        $this->page('Someone Elses Draft', ['visibility' => Visibility::Private]);
        $this->page('My Draft', ['visibility' => Visibility::Private, 'author_id' => $this->editor->id, 'publish' => false]);

        $this->actingAs($this->editor)->get('/pages/picker')
            ->assertOk()
            ->assertSee(['My Draft', 'Unpublished', 'Shared Notes'])
            ->assertDontSee('Someone Elses Draft')
            ->assertDontSee('navbar', false)
            ->assertSee('wicketPagePick', false);
    }

    public function test_every_search_term_must_match_title_slug_or_category(): void
    {
        $this->page('Server Setup', ['category' => 'ops']);
        $this->page('Server Costs', ['category' => 'finance']);
        $this->page('Ops Handbook', ['slug' => 'handbook']);

        $this->actingAs($this->editor)->withHeaders(['HX-Target' => 'page-picker'])->get('/pages/picker?q=server+ops')
            ->assertOk()
            ->assertSee('Server Setup')
            ->assertDontSee('Server Costs')
            ->assertDontSee('Ops Handbook')
            ->assertDontSee('<html', false);

        $this->actingAs($this->editor)->get('/pages/picker?q=nothing-like-this')->assertSee('No pages match');
    }

    public function test_pages_ten_at_a_time(): void
    {
        foreach (range(1, 12) as $i) {
            $this->page(sprintf('Page %02d', $i));
        }

        $this->actingAs($this->editor)->get('/pages/picker')->assertSee('Page 10')->assertDontSee('Page 11');
        $this->actingAs($this->editor)->get('/pages/picker?page=2')->assertSee('Page 11')->assertDontSee('Page 10');
    }

    public function test_suggest_returns_matching_visible_pages(): void
    {
        $this->page('Server Setup', ['slug' => 'server-setup', 'category' => 'ops']);
        $this->page('Server Secrets', ['visibility' => Visibility::Private]);
        foreach (range(1, 12) as $i) {
            $this->page("Ops note {$i}", ['category' => 'ops']);
        }

        $this->actingAs($this->editor)->getJson('/pages/picker/suggest?q=server')
            ->assertOk()
            ->assertExactJson([['slug' => 'server-setup', 'title' => 'Server Setup']]);

        $this->actingAs($this->editor)->getJson('/pages/picker/suggest?q=ops')->assertJsonCount(10);
        $this->actingAs(User::factory()->create())->getJson('/pages/picker/suggest?q=ops')->assertForbidden();
    }

    public function test_slug_for_a_new_page(): void
    {
        $this->page('Existing', ['slug' => 'existing']);

        $this->actingAs($this->editor)->getJson('/pages/picker/slug?title=Getting Started!')->assertExactJson(['slug' => 'getting-started']);
        $this->actingAs($this->editor)->getJson('/pages/picker/slug?title=Admin')->assertExactJson(['slug' => 'admin-2']);
        $this->actingAs($this->editor)->getJson('/pages/picker/slug?title=???')->assertExactJson(['slug' => 'page']);
        // An existing page is just linked to.
        $this->actingAs($this->editor)->getJson('/pages/picker/slug?title=Existing')->assertExactJson(['slug' => 'existing']);
    }

    public function test_missing_page_offers_creation_to_page_creators(): void
    {
        $this->actingAs($this->editor)->get('/getting-started')
            ->assertNotFound()
            ->assertSee('Create this page')
            ->assertSee(route('pages.create', ['title' => 'Getting started', 'slug' => 'getting-started']));

        $this->actingAs(User::factory()->create())->get('/getting-started')->assertNotFound()->assertDontSee('Create this page');
        $this->get('/getting-started')->assertNotFound()->assertDontSee('Create this page');
    }

    public function test_old_slug_redirect_beats_the_create_offer(): void
    {
        $page = $this->page('Moved', ['slug' => 'old-name']);
        $page->update(['slug' => 'new-name']);

        $this->actingAs($this->editor)->get('/old-name')->assertStatus(301)->assertRedirect('/new-name');
    }

    public function test_create_form_is_prefilled_from_the_query(): void
    {
        $this->actingAs($this->editor)->get(route('pages.create', ['title' => 'Getting started', 'slug' => 'getting-started']))
            ->assertOk()
            ->assertSee("title: 'Getting started'", false)
            ->assertSee("slug: 'getting-started'", false)
            ->assertSee('slugTouched: true', false);
    }

    public function test_page_form_has_the_picker_url(): void
    {
        $this->actingAs($this->editor)->get(route('pages.create'))
            ->assertSee('data-page-picker-url="'.route('pages.picker').'"', false)
            ->assertSee('data-page-suggest-url="'.route('pages.picker.suggest').'"', false)
            ->assertSee('data-page-slug-url="'.route('pages.picker.slug').'"', false);
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function make(string $slug, array $attrs = []): Page
    {
        $page = Page::factory()->create(['slug' => $slug, 'title' => ucfirst($slug).' Title'] + $attrs);
        Content::factory()->for($page)->create();

        return $page;
    }

    public function test_access(): void
    {
        $this->actingAs(User::factory()->editor()->create())->get('/admin/pages')->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())->post('/admin/pages/bulk', ['action' => 'trash', 'ids' => [1]])->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/pages')->assertOk();
    }

    public function test_lists_all_pages_including_private(): void
    {
        $this->make('alpha', ['visibility' => Visibility::Private]);
        $this->make('beta', ['visibility' => Visibility::Public]);

        $this->actingAs($this->admin)->get('/admin/pages')->assertSee('Alpha Title')->assertSee('Beta Title');
    }

    public function test_search_and_filters(): void
    {
        $this->make('alpha', ['category' => 'Guides', 'visibility' => Visibility::Members, 'publish' => true]);
        $this->make('beta', ['category' => 'Notes', 'visibility' => Visibility::Public, 'publish' => false]);

        $this->actingAs($this->admin);
        $this->get('/admin/pages?q=alp')->assertSee('Alpha Title')->assertDontSee('Beta Title');
        $this->get('/admin/pages?q=beta')->assertSee('Beta Title')->assertDontSee('Alpha Title');
        $this->get('/admin/pages?category=Notes')->assertSee('Beta Title')->assertDontSee('Alpha Title');
        $this->get('/admin/pages?visibility=members')->assertSee('Alpha Title')->assertDontSee('Beta Title');
        $this->get('/admin/pages?publish=no')->assertSee('Beta Title')->assertDontSee('Alpha Title');
        $this->get('/admin/pages?publish=yes')->assertSee('Alpha Title')->assertDontSee('Beta Title');
    }

    public function test_htmx_request_returns_only_the_table(): void
    {
        $this->make('alpha');

        $this->actingAs($this->admin)
            ->get('/admin/pages', ['HX-Request' => 'true', 'HX-Target' => 'page-table'])
            ->assertOk()
            ->assertSee('id="page-table"', false)
            ->assertDontSee('<html', false);
    }

    public function test_paginates(): void
    {
        foreach (range(1, 30) as $i) {
            $this->make(sprintf('page-%02d', $i));
        }

        $this->actingAs($this->admin)->get('/admin/pages')->assertSee('Page-01 Title')->assertDontSee('Page-30 Title');
        $this->actingAs($this->admin)->get('/admin/pages?page=2')->assertSee('Page-30 Title');
    }

    public function test_bulk_trash_soft_deletes(): void
    {
        $a = $this->make('alpha');
        $b = $this->make('beta');
        $keep = $this->make('gamma');

        $this->actingAs($this->admin)
            ->post('/admin/pages/bulk', ['action' => 'trash', 'ids' => [$a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertNotSoftDeleted($keep);
        $this->assertSame(1, Content::where('page_id', $a->id)->count(), 'revisions kept');

        $this->get('/alpha')->assertNotFound();
        $this->get('/admin/pages')->assertDontSee('Alpha Title')->assertSee('Gamma Title');
        $this->get('/admin/pages?status=trash')->assertSee('Alpha Title')->assertDontSee('Gamma Title');
    }

    public function test_bulk_restore(): void
    {
        $page = $this->make('alpha');
        $page->delete();

        $this->actingAs($this->admin)->post('/admin/pages/bulk', ['action' => 'restore', 'ids' => [$page->id]]);

        $this->assertNotSoftDeleted($page);
        $this->get('/alpha')->assertOk();
    }

    public function test_purge_only_affects_trashed_pages(): void
    {
        $trashed = $this->make('alpha');
        $trashed->delete();
        $live = $this->make('beta');

        $this->actingAs($this->admin)
            ->post('/admin/pages/bulk', ['action' => 'purge', 'ids' => [$trashed->id, $live->id]]);

        $this->assertDatabaseMissing('pages', ['id' => $trashed->id]);
        $this->assertSame(0, Content::where('page_id', $trashed->id)->count());
        $this->assertNotSoftDeleted($live);
    }

    public function test_bad_action_and_unknown_ids(): void
    {
        $page = $this->make('alpha');

        $this->actingAs($this->admin)->post('/admin/pages/bulk', ['action' => 'explode', 'ids' => [$page->id]])
            ->assertSessionHasErrors('action');
        $this->actingAs($this->admin)->post('/admin/pages/bulk', ['action' => 'trash', 'ids' => [99999]])
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted($page);
    }

    public function test_slug_held_by_trashed_page_gives_clear_error(): void
    {
        $this->make('alpha')->delete();

        $this->actingAs(User::factory()->editor()->create())->post('/pages', [
            'title' => 'Alpha', 'slug' => 'alpha', 'visibility' => 'public', 'publish' => '1', 'title_visible' => '1', 'html' => '',
        ])->assertSessionHasErrors(['slug' => __('A page in the trash uses this slug. Restore or permanently delete it first.')]);
    }

    public function test_auto_slug_skips_trashed_slug(): void
    {
        $this->make('alpha')->delete();

        $this->actingAs(User::factory()->editor()->create())->post('/pages', [
            'title' => 'Alpha', 'slug' => '', 'visibility' => 'public', 'publish' => '1', 'title_visible' => '1', 'html' => '',
        ])->assertRedirect('/alpha-2');
    }

    public function test_admin_landing_links_to_pages(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSee('href="'.route('admin.pages.index').'"', false);
    }
}

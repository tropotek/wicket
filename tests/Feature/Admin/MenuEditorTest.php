<?php

namespace Tests\Feature\Admin;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MenuEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function htmx(): static
    {
        return $this->actingAs($this->admin)->withHeaders(['HX-Request' => 'true']);
    }

    /** @param  array<int, ?int>  $parents  id => parent id, in display order */
    private function saveOrder(array $parents): TestResponse
    {
        return $this->htmx()->put('/admin/menu/order', [
            'order' => array_keys($parents),
            'parent' => array_map(fn ($p) => $p ?? '', $parents),
        ]);
    }

    public function test_access(): void
    {
        $editor = User::factory()->editor()->create();
        $item = MenuItem::factory()->create();

        $this->actingAs($editor)->get('/admin/menu')->assertForbidden();
        $this->actingAs($editor)->post('/admin/menu', ['type' => 'dropdown', 'name' => 'X'])->assertForbidden();
        $this->actingAs($editor)->put('/admin/menu/order', [])->assertForbidden();
        $this->actingAs($editor)->patch("/admin/menu/{$item->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($editor)->delete("/admin/menu/{$item->id}")->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/menu')->assertOk();
    }

    public function test_index_shows_the_tree_with_badges(): void
    {
        $dropdown = MenuItem::factory()->dropdown()->create(['name' => 'More']);
        MenuItem::factory()->create(['name' => 'Child', 'parent_id' => $dropdown->id, 'page_id' => Page::factory()->unpublished()->create()->id]);
        $trashed = Page::factory()->create();
        MenuItem::factory()->create(['name' => 'Gone', 'page_id' => $trashed->id]);
        $trashed->delete();
        MenuItem::factory()->divider()->create();

        $this->actingAs($this->admin)->get('/admin/menu')
            ->assertOk()
            ->assertSee('id="menu-editor"', false)
            ->assertSee(['More', 'Child', 'Gone'])
            ->assertSee(['Unpublished', 'In trash', 'Hidden in navbar']);
    }

    public function test_add_page_item_defaults_name_to_title(): void
    {
        $page = Page::factory()->create(['title' => 'Getting Started']);

        $this->htmx()->post('/admin/menu', ['type' => 'item', 'page_id' => $page->id])
            ->assertOk()
            ->assertSee('Getting Started')
            ->assertDontSee('<html', false);

        $this->assertDatabaseHas('menu_items', ['type' => 'item', 'page_id' => $page->id, 'name' => 'Getting Started', 'parent_id' => null]);
    }

    public function test_add_dropdown_and_divider_append_to_top_level(): void
    {
        MenuItem::factory()->create(['sort' => 5]);

        $this->htmx()->post('/admin/menu', ['type' => 'dropdown', 'name' => 'More'])->assertOk();
        $this->htmx()->post('/admin/menu', ['type' => 'divider'])->assertOk();

        $this->assertSame(['item', 'dropdown', 'divider'], MenuItem::orderBy('sort')->pluck('type')->map->value->all());
        $this->assertDatabaseHas('menu_items', ['type' => 'dropdown', 'name' => 'More', 'page_id' => null]);
    }

    public function test_add_validation(): void
    {
        $trashed = Page::factory()->create();
        $trashed->delete();

        $this->actingAs($this->admin)->post('/admin/menu', ['type' => 'item'])->assertSessionHasErrors('page_id');
        $this->actingAs($this->admin)->post('/admin/menu', ['type' => 'item', 'page_id' => $trashed->id])->assertSessionHasErrors('page_id');
        $this->actingAs($this->admin)->post('/admin/menu', ['type' => 'dropdown', 'name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($this->admin)->post('/admin/menu', ['type' => 'bogus'])->assertSessionHasErrors('type');

        $this->assertSame(0, MenuItem::count());
    }

    public function test_htmx_validation_error_rerenders_with_input(): void
    {
        $this->htmx()->post('/admin/menu', ['type' => 'item', 'name' => 'Kept'])
            ->assertStatus(422)
            ->assertSee('Choose a page.')
            ->assertSee('value="Kept"', false)
            ->assertDontSee('<html', false);
    }

    public function test_save_order_moves_and_resorts(): void
    {
        $a = MenuItem::factory()->create(['sort' => 1]);
        $dropdown = MenuItem::factory()->dropdown()->create(['sort' => 2]);
        $b = MenuItem::factory()->create(['parent_id' => $dropdown->id, 'sort' => 1]);
        $divider = MenuItem::factory()->divider()->create(['sort' => 3]);

        $this->saveOrder([
            $dropdown->id => null,
            $divider->id => $dropdown->id,
            $a->id => $dropdown->id,
            $b->id => null,
        ])->assertOk()->assertSee('id="menu-editor"', false);

        $this->assertSame([$dropdown->id, $b->id], MenuItem::whereNull('parent_id')->orderBy('sort')->pluck('id')->all());
        $this->assertSame([$divider->id, $a->id], $dropdown->children()->pluck('id')->all());
    }

    public function test_save_order_rejects_bad_trees(): void
    {
        $a = MenuItem::factory()->create();
        $d1 = MenuItem::factory()->dropdown()->create();
        $d2 = MenuItem::factory()->dropdown()->create();

        // dropdown inside a dropdown
        $this->saveOrder([$a->id => null, $d1->id => null, $d2->id => $d1->id])->assertStatus(422);
        // item as a parent
        $this->saveOrder([$a->id => null, $d1->id => $a->id, $d2->id => null])->assertStatus(422);
        // parent nested under another dropdown
        $this->saveOrder([$a->id => $d1->id, $d1->id => $d2->id, $d2->id => null])->assertStatus(422);
        // missing id
        $this->saveOrder([$a->id => null, $d1->id => null])->assertStatus(422);
        // unknown id
        $this->saveOrder([$a->id => null, $d1->id => null, $d2->id => null, 999 => null])->assertStatus(422);

        $this->assertSame(3, MenuItem::whereNull('parent_id')->count());
    }

    public function test_rename(): void
    {
        $item = MenuItem::factory()->create(['name' => 'Old']);

        $this->htmx()->patch("/admin/menu/{$item->id}", ['name' => 'New'])->assertOk()->assertSee('New');

        $this->assertSame('New', $item->fresh()->name);
        $this->htmx()->patch("/admin/menu/{$item->id}", ['name' => ''])->assertStatus(422);
    }

    public function test_delete_dropdown_removes_children(): void
    {
        $dropdown = MenuItem::factory()->dropdown()->create();
        MenuItem::factory()->count(2)->create(['parent_id' => $dropdown->id]);
        $keep = MenuItem::factory()->create();

        $this->htmx()->delete("/admin/menu/{$dropdown->id}")->assertOk();

        $this->assertSame([$keep->id], MenuItem::pluck('id')->all());
    }

    public function test_non_htmx_writes_redirect_back(): void
    {
        $this->actingAs($this->admin)
            ->from('/admin/menu')
            ->post('/admin/menu', ['type' => 'dropdown', 'name' => 'More'])
            ->assertRedirect('/admin/menu');

        $this->assertSame(MenuItemType::Dropdown, MenuItem::first()->type);
    }
}

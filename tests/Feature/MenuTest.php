<?php

namespace Tests\Feature;

use App\Enums\MenuItemType;
use App\Enums\Visibility;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use App\Support\Menu;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    private function page(Visibility $visibility = Visibility::Public, array $attrs = []): Page
    {
        return Page::factory()->create(['visibility' => $visibility] + $attrs);
    }

    private function item(string $name, ?Page $page = null, array $attrs = []): MenuItem
    {
        return MenuItem::factory()->create(['name' => $name, 'page_id' => ($page ?? $this->page())->id] + $attrs);
    }

    /** @return list<string> top-level names, dropdowns as "Name[child, child]" ("-" for a divider) */
    private function names(?User $user = null): array
    {
        return app(Menu::class)->for($user)->map(fn (MenuItem $i) => $i->type === MenuItemType::Dropdown
            ? $i->name.'['.$i->children->map(fn (MenuItem $c) => $c->type === MenuItemType::Divider ? '-' : $c->name)->implode(', ').']'
            : $i->name)->all();
    }

    public function test_purging_a_page_drops_its_items(): void
    {
        $page = $this->page();
        $this->item('Link', $page);

        $page->forceDelete();

        $this->assertSame(0, MenuItem::count());
    }

    public function test_deleting_a_dropdown_drops_its_children(): void
    {
        $dropdown = MenuItem::factory()->dropdown()->create();
        $this->item('Child', attrs: ['parent_id' => $dropdown->id]);

        $dropdown->delete();

        $this->assertSame(0, MenuItem::count());
    }

    public function test_items_are_ordered_by_sort(): void
    {
        $this->item('Second', attrs: ['sort' => 2]);
        $this->item('First', attrs: ['sort' => 1]);

        $this->assertSame(['First', 'Second'], $this->names());
    }

    public function test_items_follow_page_visibility(): void
    {
        $author = User::factory()->editor()->create();
        $this->item('Public', $this->page(Visibility::Public));
        $this->item('Members', $this->page(Visibility::Members));
        $this->item('Editors', $this->page(Visibility::Editors));
        $this->item('Private', $this->page(Visibility::Private, ['author_id' => $author->id]));

        $this->assertSame(['Public'], $this->names());
        $this->assertSame(['Public', 'Members'], $this->names(User::factory()->create()));
        $this->assertSame(['Public', 'Members', 'Editors'], $this->names(User::factory()->editor()->create()));
        $this->assertSame(['Public', 'Members', 'Editors', 'Private'], $this->names($author));
        $this->assertSame(['Public', 'Members', 'Editors', 'Private'], $this->names(User::factory()->admin()->create()));
    }

    public function test_unpublished_trashed_and_pageless_items_are_hidden(): void
    {
        $this->item('Unpublished', $this->page(attrs: ['publish' => false]));
        $trashed = $this->page();
        $this->item('Trashed', $trashed);
        $trashed->delete();
        MenuItem::factory()->create(['name' => 'No page', 'page_id' => null]);
        $this->item('Shown');

        $this->assertSame(['Shown'], $this->names());
    }

    public function test_dropdown_without_visible_items_is_hidden(): void
    {
        $dropdown = MenuItem::factory()->dropdown()->create(['name' => 'More']);
        $this->item('Members', $this->page(Visibility::Members), ['parent_id' => $dropdown->id]);
        MenuItem::factory()->divider()->create(['parent_id' => $dropdown->id]);

        $this->assertSame([], $this->names());
        $this->assertSame(['More[Members]'], $this->names(User::factory()->create()));
    }

    public function test_stray_dividers_are_dropped(): void
    {
        $dropdown = MenuItem::factory()->dropdown()->create(['name' => 'More']);
        $child = fn (array $attrs) => ['parent_id' => $dropdown->id] + $attrs;

        MenuItem::factory()->divider()->create($child(['sort' => 1]));
        $this->item('A', attrs: $child(['sort' => 2]));
        MenuItem::factory()->divider()->create($child(['sort' => 3]));
        $this->item('Hidden', $this->page(Visibility::Members), $child(['sort' => 4]));
        MenuItem::factory()->divider()->create($child(['sort' => 5]));
        $this->item('B', attrs: $child(['sort' => 6]));
        MenuItem::factory()->divider()->create($child(['sort' => 7]));
        MenuItem::factory()->divider()->create(['sort' => 8]);

        $this->assertSame(['More[A, -, B]'], $this->names());
    }

    public function test_navbar_renders_items_dropdowns_and_active_link(): void
    {
        $home = $this->page(attrs: ['slug' => 'home-page']);
        $this->item('Home', $home, ['sort' => 1]);
        $dropdown = MenuItem::factory()->dropdown()->create(['name' => 'More', 'sort' => 2]);
        $this->item('Docs', $this->page(attrs: ['slug' => 'docs']), ['parent_id' => $dropdown->id, 'sort' => 1]);
        MenuItem::factory()->divider()->create(['parent_id' => $dropdown->id, 'sort' => 2]);
        $this->item('Faq', $this->page(attrs: ['slug' => 'faq']), ['parent_id' => $dropdown->id, 'sort' => 3]);

        $this->get('/home-page')
            ->assertOk()
            ->assertSeeInOrder(['class="nav-link active"', 'href="'.url('/home-page').'"', 'aria-current="page"', 'Home'], false)
            ->assertSeeInOrder([
                'nav-link dropdown-toggle', 'More',
                'dropdown-item', url('/docs'), 'Docs',
                'dropdown-divider',
                'dropdown-item', url('/faq'), 'Faq',
            ], false);
    }

    public function test_no_menu_markup_without_items(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('id="main-menu"', false);
    }

    public function test_dev_seeder_builds_the_sample_menu(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(['Public'], $this->names());
        $this->assertSame(
            ['Public', 'More[Members, Editors, -, Private]'],
            $this->names(User::where('username', 'admin')->first()),
        );
    }
}

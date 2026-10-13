<?php

namespace Database\Seeders;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Sample navbar over the dev pages: one link, one dropdown with a divider.
 */
class DevMenuSeeder extends Seeder
{
    public function run(): void
    {
        $page = fn (string $slug) => Page::where('slug', $slug)->value('id');

        MenuItem::create(['type' => MenuItemType::Item, 'name' => 'Public', 'page_id' => $page('public-page'), 'sort' => 1]);

        $more = MenuItem::create(['type' => MenuItemType::Dropdown, 'name' => 'More', 'sort' => 2]);

        $children = [
            [MenuItemType::Item, 'Members', 'members-page'],
            [MenuItemType::Item, 'Editors', 'editors-page'],
            [MenuItemType::Divider, '', null],
            [MenuItemType::Item, 'Private', 'private-page'],
            [MenuItemType::Item, 'Secret tiles', 'secret-tiles'],
        ];

        foreach ($children as $i => [$type, $name, $slug]) {
            MenuItem::create([
                'parent_id' => $more->id,
                'type' => $type,
                'name' => $name,
                'page_id' => $slug ? $page($slug) : null,
                'sort' => $i + 1,
            ]);
        }
    }
}

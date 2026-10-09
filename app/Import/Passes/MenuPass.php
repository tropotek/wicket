<?php

namespace App\Import\Passes;

use App\Enums\MenuItemType;
use App\Import\ImportContext;
use App\Import\Pass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** tkwiki `menu_item` → menu_items, parents before children; `order_id` becomes `sort`. */
class MenuPass implements Pass
{
    public function name(): string
    {
        return 'Menu';
    }

    public function count(ImportContext $context): int
    {
        return $context->source->db()->table('menu_item')->count();
    }

    public function run(ImportContext $context): int
    {
        $rows = $context->source->db()->table('menu_item')
            ->orderByRaw('parent_id IS NOT NULL')->orderBy('order_id')->orderBy('menu_item_id')->get();
        $now = Carbon::now();
        $imported = 0;

        foreach ($rows as $row) {
            $type = MenuItemType::tryFrom((string) $row->type);
            $parentId = $context->newId('menu', $row->parent_id === null ? null : (int) $row->parent_id);
            $pageId = $context->newId('page', $row->page_id === null ? null : (int) $row->page_id);

            $problem = match (true) {
                $type === null => "has unknown type \"{$row->type}\"",
                $row->parent_id !== null && $parentId === null => 'sits under a menu item that wasn\'t imported',
                $type === MenuItemType::Item && $pageId === null => 'links to a page that doesn\'t exist',
                default => null,
            };
            if ($problem !== null) {
                $context->warn("Menu item \"{$row->name}\" {$problem}; skipped.");

                continue;
            }

            $id = DB::table('menu_items')->insertGetId([
                'parent_id' => $parentId,
                'page_id' => $type === MenuItemType::Item ? $pageId : null,
                'sort' => (int) $row->order_id,
                'type' => $type->value,
                'name' => (string) $row->name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $context->map('menu', (int) $row->menu_item_id, $id);
            $imported++;
        }

        return $imported;
    }
}

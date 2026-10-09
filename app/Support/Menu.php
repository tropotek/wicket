<?php

namespace App\Support;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * The navbar menu as $user sees it: two levels, hidden pages and empty dropdowns removed.
 */
class Menu
{
    /**
     * Top-level items and dropdowns. A dropdown's visible entries are in its `children` relation.
     *
     * @return Collection<int, MenuItem>
     */
    public function for(?User $user): Collection
    {
        $byParent = MenuItem::with('page')->orderBy('sort')->orderBy('id')->get()->groupBy('parent_id');

        return ($byParent[''] ?? collect())
            ->map(function (MenuItem $item) use ($byParent, $user) {
                if ($item->type === MenuItemType::Dropdown) {
                    $children = $this->trimDividers(($byParent[$item->id] ?? collect())
                        ->filter(fn (MenuItem $child) => $child->type === MenuItemType::Divider || $this->visible($child, $user)));

                    return $children->contains(fn (MenuItem $c) => $c->type === MenuItemType::Item)
                        ? $item->setRelation('children', $children)
                        : null;
                }

                return $this->visible($item, $user) ? $item : null;
            })
            ->filter()
            ->values();
    }

    /** A page link whose page is live, published and readable by $user. */
    private function visible(MenuItem $item, ?User $user): bool
    {
        return $item->type === MenuItemType::Item
            && $item->page !== null
            && $item->page->publish
            && Gate::forUser($user)->allows('view', $item->page);
    }

    /**
     * Drop leading, trailing and repeated dividers.
     *
     * @param  Collection<int, MenuItem>  $items
     * @return Collection<int, MenuItem>
     */
    private function trimDividers(Collection $items): Collection
    {
        $out = collect();

        foreach ($items as $item) {
            $isDivider = $item->type === MenuItemType::Divider;
            if ($isDivider && ($out->isEmpty() || $out->last()->type === MenuItemType::Divider)) {
                continue;
            }
            $out->push($item);
        }

        if ($out->last()?->type === MenuItemType::Divider) {
            $out->pop();
        }

        return $out->values();
    }
}

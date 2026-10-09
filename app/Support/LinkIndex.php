<?php

namespace App\Support;

use App\Html\PageLinks;
use App\Models\Page;
use App\Models\PageLink;
use Illuminate\Support\Facades\DB;

/** Keeps page_links in step with each page's current revision. */
class LinkIndex
{
    public function __construct(private PageLinks $links) {}

    public function sync(Page $page, ?string $html): void
    {
        $slugs = array_values(array_filter($this->links->slugsIn((string) $html), fn (string $s) => $s !== $page->slug));

        DB::transaction(function () use ($page, $slugs) {
            PageLink::where('page_id', $page->id)->delete();
            PageLink::insert(array_map(fn (string $slug) => ['page_id' => $page->id, 'slug' => $slug], $slugs));
        });
    }
}

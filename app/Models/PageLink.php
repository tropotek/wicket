<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Page X links to /slug", from X's current revision. Maintained by App\Support\LinkIndex.
 */
class PageLink extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = [];

    /**
     * Links whose slug reaches no live page, directly or through an old-slug redirect,
     * from pages that are themselves live.
     *
     * @param  Builder<PageLink>  $query
     */
    #[Scope]
    protected function broken(Builder $query): void
    {
        $query
            ->whereHas('page')
            ->whereNotExists(fn ($q) => $q->from('pages')
                ->whereColumn('pages.slug', 'page_links.slug')
                ->whereNull('pages.deleted_at'))
            ->whereNotExists(fn ($q) => $q->from('page_redirects')
                ->join('pages', 'pages.id', '=', 'page_redirects.page_id')
                ->whereColumn('page_redirects.slug', 'page_links.slug')
                ->whereNull('pages.deleted_at'));
    }

    /** The linking page. @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}

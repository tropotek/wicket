<?php

namespace App\Models;

use App\Enums\Visibility;
use App\Support\RenderCache;
use App\Support\SearchIndex;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * author_id and views are deliberately not fillable: set them explicitly.
 */
#[Fillable(['title', 'slug', 'category', 'template', 'title_visible', 'visibility', 'publish'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory, SoftDeletes;

    /** Mirrors the column defaults so a freshly created model has them without a refresh. */
    protected $attributes = [
        'category' => '',
        'template' => '',
        'visibility' => 'private',
        'publish' => true,
        'title_visible' => true,
        'views' => 0,
    ];

    /**
     * Keep page_redirects in step with slugs: a rename records the old slug, and a live
     * page always owns its own slug (renaming back, or a new page claiming an old slug).
     */
    protected static function booted(): void
    {
        static::saved(function (Page $page) {
            if ($page->wasChanged('slug') && $old = $page->getOriginal('slug')) {
                PageRedirect::updateOrCreate(['slug' => $old], ['page_id' => $page->id]);
            }

            PageRedirect::where('slug', $page->slug)->delete();

            if ($page->wasRecentlyCreated || $page->wasChanged(['title', 'category'])) {
                app(SearchIndex::class)->sync($page);
            }
        });

        // Trashed pages leave search; restored ones come back. (New revisions index via Content.)
        static::deleted(fn (Page $page) => app(SearchIndex::class)->remove($page->id));
        static::restored(fn (Page $page) => app(SearchIndex::class)->sync($page));

        // Guest renders read titles, slugs and existence of linked pages: any change retires them.
        // (Redirects only change inside a page save; revisions flush from Content.)
        static::saved(fn () => app(RenderCache::class)->flush());
        static::deleted(fn () => app(RenderCache::class)->flush());
        static::restored(fn () => app(RenderCache::class)->flush());
    }

    protected function casts(): array
    {
        return [
            'visibility' => Visibility::class,
            'publish' => 'boolean',
            'title_visible' => 'boolean',
            'views' => 'integer',
        ];
    }

    /**
     * Pages $user may read: the query form of PagePolicy::view().
     *
     * @param  Builder<Page>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, ?User $user): void
    {
        if ($user?->isAdmin()) {
            return;
        }

        $byRole = collect(Visibility::cases())
            ->filter(fn (Visibility $v) => ($min = $v->minimumRole()) && $user?->hasRole($min))
            ->map->value
            ->push(Visibility::Public->value);

        $query->where(fn (Builder $q) => $q
            ->whereIn('visibility', $byRole->all())
            ->when($user, fn (Builder $q) => $q->orWhere('author_id', $user->id)));
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** All revisions, oldest first. @return HasMany<Content, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class)->orderBy('id');
    }

    /** Page slugs this page links to (current revision). @return HasMany<PageLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(PageLink::class);
    }

    /**
     * Live pages the viewer may see whose current revision links here — by the current slug or an
     * old one (task 37 redirects).
     *
     * @return Builder<Page>
     */
    public function backlinks(?User $viewer): Builder
    {
        $slugs = PageRedirect::where('page_id', $this->id)->select('slug')
            ->union(Page::query()->whereKey($this->id)->select('slug'));

        // Page::query() — inside this class a static call would hit the protected scope method directly.
        return Page::query()->visibleTo($viewer)
            ->whereKeyNot($this->id)
            ->whereIn('id', PageLink::query()->select('page_id')->whereIn('slug', $slugs))
            ->orderBy('title');
    }

    /** The current revision (highest id). @return HasOne<Content, $this> */
    public function latestContent(): HasOne
    {
        return $this->hasOne(Content::class)->latestOfMany();
    }
}

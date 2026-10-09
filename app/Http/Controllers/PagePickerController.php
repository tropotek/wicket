<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\ReservedSlugs;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The editor's wikiPage dialog: pick a page to link to, or name a new one (created when the
 * link is first followed).
 */
class PagePickerController extends Controller
{
    /** Full dialog page, or the #page-picker results for HTMX searches and paging. */
    public function index(Request $request): View
    {
        Gate::authorize('create', Page::class);

        $q = trim((string) $request->query('q'));
        $pages = $this->search($request, $q)->paginate(10)->withQueryString();

        $view = $request->header('HX-Target') === 'page-picker' ? 'pages.picker._results' : 'pages.picker.index';

        return view($view, ['pages' => $pages, 'q' => $q]);
    }

    /** `[[` autocomplete in the editor: up to 10 matching pages as {slug, title}. */
    public function suggest(Request $request): JsonResponse
    {
        Gate::authorize('create', Page::class);

        return response()->json(
            $this->search($request, trim((string) $request->query('q')))->limit(10)->get(['slug', 'title'])
        );
    }

    /** Slug a new page with this title would get: the title slugged, stepped past reserved slugs. */
    public function slug(Request $request, ReservedSlugs $reserved): JsonResponse
    {
        Gate::authorize('create', Page::class);

        $base = Str::slug((string) $request->query('title')) ?: 'page';
        $slug = $base;

        for ($n = 2; $reserved->contains($slug); $n++) {
            $slug = "{$base}-{$n}";
        }

        return response()->json(['slug' => $slug]);
    }

    /**
     * Pages the user can see where every whitespace-separated term matches title, slug or category.
     *
     * @return Builder<Page>
     */
    private function search(Request $request, string $q): Builder
    {
        $terms = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);

        return Page::visibleTo($request->user())
            ->where(function (Builder $query) use ($terms) {
                foreach ($terms as $term) {
                    $query->where(fn (Builder $w) => $w
                        ->where('title', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%")
                        ->orWhere('category', 'like', "%{$term}%"));
                }
            })
            ->orderBy('title');
    }
}

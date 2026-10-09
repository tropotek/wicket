<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Support\SearchIndex;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SearchController extends Controller
{
    private const PER_PAGE = 20;

    /** Snippet markers: control characters can't occur in indexed text, so escaping can't touch them. */
    private const HIT_START = "\x02";

    private const HIT_END = "\x03";

    /** Full page, or the #search-results partial for HTMX type-ahead and paging. */
    public function index(Request $request, SearchIndex $index): View
    {
        $q = trim((string) $request->query('q'));
        $terms = $this->terms($q);

        $results = $terms === []
            ? new LengthAwarePaginator([], 0, self::PER_PAGE)
            : ($index->enabled() ? $this->fullText($request, $terms) : $this->like($request, $terms));
        $results->withQueryString()->setPath(route('search'));

        $view = $request->header('HX-Target') === 'search-results' ? 'search._results' : 'search.index';

        return view($view, ['q' => $q, 'searched' => $terms !== [], 'results' => $results]);
    }

    /**
     * Words of the query as plain tokens (letters and digits only), so nothing the user types is
     * read as FTS syntax.
     *
     * @return list<string>
     */
    private function terms(string $q): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $q, $m);

        return array_slice(array_values(array_unique(array_map('mb_strtolower', $m[0]))), 0, 10);
    }

    /** @param  list<string>  $terms */
    private function fullText(Request $request, array $terms): LengthAwarePaginator
    {
        $match = implode(' ', array_map(fn (string $t) => '"'.$t.'"*', $terms));

        $results = Page::visibleTo($request->user())
            ->join('pages_fts', 'pages_fts.rowid', '=', 'pages.id')
            ->whereRaw('pages_fts MATCH ?', [$match])
            ->select('pages.*')
            ->selectRaw('snippet(pages_fts, 2, ?, ?, ?, 16) AS snippet', [self::HIT_START, self::HIT_END, '…'])
            ->with('latestContent')
            ->orderByRaw('bm25(pages_fts, 10.0, 4.0, 1.0, 2.0)')
            ->paginate(self::PER_PAGE);

        $results->getCollection()->each(fn (Page $page) => $page->setAttribute('snippet_html', $this->highlight((string) $page->snippet, $page)));

        return $results;
    }

    /** @param  list<string>  $terms */
    private function like(Request $request, array $terms): LengthAwarePaginator
    {
        $query = Page::visibleTo($request->user())->with('latestContent');
        foreach ($terms as $term) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$term}%")->orWhere('category', 'like', "%{$term}%"));
        }

        $results = $query->orderBy('title')->paginate(self::PER_PAGE);
        $results->getCollection()->each(fn (Page $page) => $page->setAttribute('snippet_html', e((string) $page->latestContent?->description)));

        return $results;
    }

    /** Escaped snippet with hits in <mark>; the description when the body didn't match. */
    private function highlight(string $snippet, Page $page): string
    {
        if (! str_contains($snippet, self::HIT_START)) {
            return e((string) $page->latestContent?->description);
        }

        return str_replace([self::HIT_START, self::HIT_END], ['<mark>', '</mark>'], e($snippet));
    }
}

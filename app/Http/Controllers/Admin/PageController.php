<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Visibility;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Full page, or just the table when HTMX targets #page-table (filters, paging).
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:128'],
            'visibility' => ['nullable', Rule::enum(Visibility::class)],
            'publish' => ['nullable', Rule::in(['yes', 'no'])],
            'status' => ['nullable', Rule::in(['active', 'trash'])],
        ]);
        $q = trim($filters['q'] ?? '');
        $trash = ($filters['status'] ?? null) === 'trash';

        $pages = Page::query()
            ->with('author')
            ->when($trash, fn ($query) => $query->onlyTrashed())
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%")))
            ->when($filters['category'] ?? null, fn ($query, $c) => $query->where('category', $c))
            ->when($filters['visibility'] ?? null, fn ($query, $v) => $query->where('visibility', $v))
            ->when($filters['publish'] ?? null, fn ($query, $p) => $query->where('publish', $p === 'yes'))
            ->orderBy('title')
            ->paginate(25)
            ->withQueryString();

        $view = $request->header('HX-Target') === 'page-table' ? 'admin.pages._table' : 'admin.pages.index';

        return view($view, [
            'pages' => $pages,
            'filters' => $filters + ['q' => $q],
            'trash' => $trash,
            'categories' => Page::withTrashed()->where('category', '!=', '')->distinct()->orderBy('category')->pluck('category'),
            'visibilities' => Visibility::cases(),
        ]);
    }

    /**
     * trash: live pages → trash. restore / purge: trashed pages only.
     * Each page is authorised on its own.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['trash', 'restore', 'purge'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $query = $data['action'] === 'trash' ? Page::query() : Page::onlyTrashed();
        $pages = $query->whereKey($data['ids'])->get();

        [$ability, $apply, $message] = match ($data['action']) {
            'trash' => ['delete', fn (Page $p) => $p->delete(), ':count page moved to the trash.|:count pages moved to the trash.'],
            'restore' => ['restore', fn (Page $p) => $p->restore(), ':count page restored.|:count pages restored.'],
            'purge' => ['forceDelete', fn (Page $p) => $p->forceDelete(), ':count page permanently deleted.|:count pages permanently deleted.'],
        };

        $pages->each(fn (Page $p) => Gate::authorize($ability, $p));
        $pages->each($apply);

        return back()->with('status', trans_choice($message, $pages->count()));
    }
}

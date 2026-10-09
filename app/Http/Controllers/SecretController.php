<?php

namespace App\Http\Controllers;

use App\Http\Requests\SecretRequest;
use App\Models\Secret;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecretController extends Controller implements HasMiddleware
{
    /** The whole module 404s while switched off. */
    public static function middleware(): array
    {
        return [
            new Middleware(fn (Request $request, Closure $next) => config('wicket.secrets') ? $next($request) : abort(404)),
        ];
    }

    /** Full page, or just the table when HTMX targets #secret-table (filters, paging). */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Secret::class);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', Rule::in(array_map(fn ($v) => $v->value, Secret::visibilities()))],
            'otp' => ['nullable', Rule::in(['yes', 'no'])],
        ]);
        $q = trim($filters['q'] ?? '');

        $secrets = Secret::visibleTo($request->user())
            ->with('author')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->when($filters['visibility'] ?? null, fn ($query, $v) => $query->where('visibility', $v))
            ->when($filters['otp'] ?? null, fn ($query, $o) => $o === 'yes' ? $query->whereNotNull('otp') : $query->whereNull('otp'))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $view = $request->header('HX-Target') === 'secret-table' ? 'secrets._table' : 'secrets.index';

        return view($view, [
            'secrets' => $secrets,
            'filters' => $filters + ['q' => $q],
            'visibilities' => Secret::visibilities(),
        ]);
    }

    /**
     * The editor's wikiSecret dialog: pick a secret to embed. `pick=<id>` (after creating one
     * from the dialog) posts that secret straight back to the editor.
     */
    public function picker(Request $request): View
    {
        Gate::authorize('viewAny', Secret::class);

        $q = trim((string) $request->query('q'));
        $secrets = Secret::visibleTo($request->user())
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        $picked = $request->filled('pick') ? Secret::visibleTo($request->user())->find($request->integer('pick')) : null;

        $view = $request->header('HX-Target') === 'secret-picker' ? 'secrets.picker._results' : 'secrets.picker.index';

        return view($view, ['secrets' => $secrets, 'q' => $q, 'picked' => $picked]);
    }

    /** `picker=1`: inside the editor dialog (bare layout; saving inserts the new secret). */
    public function create(Request $request): View
    {
        Gate::authorize('create', Secret::class);

        return view('secrets.form', [
            'secret' => new Secret,
            'visibilities' => Secret::visibilities(),
            'picker' => $request->boolean('picker'),
        ]);
    }

    /** Authorised by SecretRequest. */
    public function store(SecretRequest $request): RedirectResponse
    {
        $secret = new Secret($request->secretAttributes());
        $secret->author()->associate($request->user());
        $secret->save();

        if ($request->boolean('picker')) {
            return redirect()->route('secrets.picker', ['pick' => $secret->id]);
        }

        return redirect()->route('secrets.index')->with('status', __('Secret ":name" created.', ['name' => $secret->name]));
    }

    public function edit(Secret $secret): View
    {
        Gate::authorize('update', $secret);

        return view('secrets.form', ['secret' => $secret, 'visibilities' => Secret::visibilities(), 'picker' => false]);
    }

    /** Authorised by SecretRequest. */
    public function update(SecretRequest $request, Secret $secret): RedirectResponse
    {
        $secret->update($request->secretAttributes());

        return redirect()->route('secrets.index')->with('status', __('Secret ":name" saved.', ['name' => $secret->name]));
    }

    public function destroy(Secret $secret): RedirectResponse
    {
        Gate::authorize('delete', $secret);

        $secret->delete();

        return back()->with('status', __('Secret ":name" deleted.', ['name' => $secret->name]));
    }
}

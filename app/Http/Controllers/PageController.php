<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Http\Requests\PageRequest;
use App\Models\Page;
use App\Support\ReservedSlugs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

use function Illuminate\Support\defer;

class PageController extends Controller
{
    public function show(Request $request, Page $page): View|RedirectResponse
    {
        if (Gate::denies('view', $page)) {
            // Logged-out visitors get a chance to log in; everyone else is refused.
            abort_if($request->user() !== null, 403);

            return redirect()->guest(route('login'));
        }

        $page->load(['latestContent.user', 'author']);

        // After the response, and via the base query so updated_at isn't touched.
        defer(fn () => Page::whereKey($page->id)->toBase()->increment('views'));

        return view('pages.show', [
            'page' => $page,
            'content' => $page->latestContent,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Page::class);

        $page = new Page(['visibility' => Visibility::Private]);

        return view('pages.form', $this->formData($request, $page));
    }

    /** Authorised by PageRequest. */
    public function store(PageRequest $request): RedirectResponse
    {
        $page = DB::transaction(function () use ($request) {
            $page = new Page($this->pageAttributes($request));
            $page->slug = $request->validated('slug') ?: $this->uniqueSlug($request->validated('title'));
            $page->author()->associate($request->user());
            $page->save();

            $this->appendRevision($request, $page);

            return $page;
        });

        return redirect()->route('pages.show', $page)->with('status', __('Page created.'));
    }

    public function edit(Request $request, Page $page): View
    {
        Gate::authorize('update', $page);

        $page->load('latestContent');

        return view('pages.form', $this->formData($request, $page));
    }

    /** Authorised by PageRequest. */
    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        DB::transaction(function () use ($request, $page) {
            $page->fill($this->pageAttributes($request));
            $page->slug = $request->validated('slug') ?: $page->slug;
            $page->save();

            $current = $page->latestContent;
            $changed = $current === null
                || $current->html !== (string) $request->validated('html')
                || $current->description !== (string) $request->validated('description')
                || $current->keywords !== (string) $request->validated('keywords');

            if ($changed) {
                $this->appendRevision($request, $page);
            }
        });

        return redirect()->route('pages.show', $page)->with('status', __('Page saved.'));
    }

    /** @return array<string, mixed> */
    private function pageAttributes(PageRequest $request): array
    {
        return [
            'title' => $request->validated('title'),
            'category' => (string) $request->validated('category'),
            'visibility' => Visibility::from($request->validated('visibility')),
            'publish' => $request->boolean('publish'),
            'title_visible' => $request->boolean('title_visible'),
        ];
    }

    private function appendRevision(PageRequest $request, Page $page): void
    {
        $page->contents()->make([
            'html' => (string) $request->validated('html'),
            'description' => (string) $request->validated('description'),
            'keywords' => (string) $request->validated('keywords'),
        ])->user()->associate($request->user())->save();

        $page->unsetRelation('latestContent');
    }

    /** Slug from the title, suffixed -2, -3… past taken and reserved slugs. */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'page';
        $reserved = app(ReservedSlugs::class);

        $slug = $base;
        $n = 1;

        while ($reserved->contains($slug) || Page::where('slug', $slug)->exists()) {
            $slug = $base.'-'.++$n;
        }

        return $slug;
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, Page $page): array
    {
        return [
            'page' => $page,
            'content' => $page->latestContent,
            'visibilities' => Visibility::cases(),
            'categories' => Page::visibleTo($request->user())
                ->where('category', '!=', '')
                ->distinct()->orderBy('category')->pluck('category'),
        ];
    }
}

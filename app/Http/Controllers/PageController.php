<?php

namespace App\Http\Controllers;

use App\Enums\Visibility;
use App\Facades\Settings;
use App\Html\Pipeline;
use App\Html\RenderContext;
use App\Http\Requests\PageRequest;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Support\PageLocker;
use App\Support\RenderCache;
use App\Support\ReservedSlugs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

use function Illuminate\Support\defer;

class PageController extends Controller
{
    /** Backlinks listed under a page before "and more". */
    private const BACKLINKS = 20;

    /** `/`: the home page chosen in Settings, when it exists and the visitor may read it. */
    public function home(Request $request, Pipeline $pipeline, RenderCache $cache): View|RedirectResponse
    {
        $page = Page::find(Settings::get('wiki.home_page'));

        if ($page === null || Gate::denies('view', $page)) {
            return view('welcome');
        }

        return $this->show($request, $page, $pipeline, $cache);
    }

    public function show(Request $request, Page $page, Pipeline $pipeline, RenderCache $cache): View|RedirectResponse
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
            'html' => $this->render($page, $request, $pipeline, $cache),
            'lock' => $request->user() ? app(PageLocker::class)->heldByOther($page, $request->user()) : null,
            ...$this->backlinks($page, $request),
        ]);
    }

    /**
     * No live page has this slug: 301 an old slug to its page's current one. Otherwise 404, which
     * offers to create the page to anyone who can (links to pages not written yet land here).
     */
    public static function redirectOldSlug(Request $request): Response|RedirectResponse
    {
        $slug = (string) $request->route('page');
        $page = PageRedirect::with('page')->firstWhere('slug', $slug)?->page;

        if ($page !== null) {
            $url = route('pages.show', $page);
            $query = $request->getQueryString();

            return redirect($query ? $url.'?'.$query : $url, 301);
        }

        abort_unless($request->user()?->can('create', Page::class), 404);

        return response()->view('pages.missing', [
            'slug' => $slug,
            'title' => Str::of($slug)->replace(['-', '_'], ' ')->ucfirst()->toString(),
        ], 404);
    }

    /** @return array{backlinks: Collection<int, Page>, backlinkCount: int} */
    private function backlinks(Page $page, Request $request): array
    {
        if (! Settings::get('wiki.backlinks')) {
            return ['backlinks' => collect(), 'backlinkCount' => 0];
        }

        $query = $page->backlinks($request->user());

        return [
            'backlinks' => (clone $query)->limit(self::BACKLINKS)->get(['id', 'title', 'slug']),
            'backlinkCount' => $query->count(),
        ];
    }

    /** Rendered content; cached for guests, whose render doesn't depend on who they are. */
    private function render(Page $page, Request $request, Pipeline $pipeline, RenderCache $cache): string
    {
        $content = $page->latestContent;

        if ($content === null) {
            return '';
        }

        $render = fn () => $pipeline->render($content->html, new RenderContext($page, $request->user()));

        return $request->user() ? $render() : $cache->forGuest($content, $render);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Page::class);

        // Prefilled when following a link to a page that doesn't exist yet.
        $page = new Page([
            'visibility' => Visibility::Private,
            'title' => (string) $request->query('title'),
            'slug' => (string) $request->query('slug'),
        ]);

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

    public function edit(Request $request, Page $page, PageLocker $locker): Response|View
    {
        Gate::authorize('update', $page);

        if (! $locker->acquire($page, $request->user())) {
            return $this->locked($page, $locker);
        }

        $page->load('latestContent');

        return view('pages.form', $this->formData($request, $page) + [
            'heartbeatSeconds' => intdiv($locker->seconds(), 2),
        ]);
    }

    /** Authorised by PageRequest. Needs the edit lock, or a free page. */
    public function update(PageRequest $request, Page $page, PageLocker $locker): Response|RedirectResponse
    {
        if (! $locker->acquire($page, $request->user())) {
            return $this->locked($page, $locker);
        }

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

        $locker->release($page, $request->user());

        return redirect()->route('pages.show', $page)->with('status', __('Page saved.'));
    }

    /** Editor heartbeat: keeps the lock alive, or reports that it was lost. */
    public function heartbeat(Request $request, Page $page, PageLocker $locker): Response
    {
        Gate::authorize('update', $page);

        if ($locker->acquire($page, $request->user())) {
            return response('');
        }

        return response()->view('pages._lock-lost', ['lock' => $locker->holder($page)]);
    }

    /** Cancel editing: release the lock and go back to the page. */
    public function unlock(Request $request, Page $page, PageLocker $locker): RedirectResponse
    {
        Gate::authorize('update', $page);

        $locker->release($page, $request->user());

        return redirect()->route('pages.show', $page);
    }

    private function locked(Page $page, PageLocker $locker): Response
    {
        return response()->view('pages.locked', ['page' => $page, 'lock' => $locker->holder($page)], 423);
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

        while ($reserved->contains($slug) || Page::withTrashed()->where('slug', $slug)->exists()) {
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

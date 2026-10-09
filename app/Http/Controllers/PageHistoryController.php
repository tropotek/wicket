<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\Page;
use App\Support\PageLocker;
use App\Support\RevisionDiff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Revision history for editors: list, diff, view and restore. Needs `update`
 * on the page — old revisions may hold content deliberately removed.
 */
class PageHistoryController extends Controller
{
    public function index(Page $page): View
    {
        Gate::authorize('update', $page);

        $revisions = $page->contents()->with('user')->get();

        return view('pages.history', [
            'page' => $page,
            // Newest first, keyed by 1-based revision number.
            'revisions' => $revisions->values()->mapWithKeys(fn (Content $c, int $i) => [$i + 1 => $c])->reverse(),
            'currentId' => $revisions->last()?->id,
        ]);
    }

    public function diff(Request $request, Page $page, RevisionDiff $differ): View
    {
        Gate::authorize('update', $page);

        $ids = $page->contents()->pluck('id');
        abort_if($ids->isEmpty(), 404);

        $toId = (int) $request->query('to', $ids->last());
        $fromId = (int) $request->query('from', $ids->get($ids->search($toId) - 1) ?? $toId);

        $from = $page->contents()->findOrFail($fromId);
        $to = $page->contents()->findOrFail($toId);
        $rows = $differ->rows($from->html, $to->html);

        return view('pages.diff', [
            'page' => $page,
            'from' => $from,
            'to' => $to,
            'numbers' => $ids->flip()->map(fn (int $i) => $i + 1),
            'rows' => $rows,
            'changed' => $differ->hasChanges($rows),
        ]);
    }

    public function show(Page $page, Content $content): View
    {
        Gate::authorize('update', $page);

        $ids = $page->contents()->pluck('id');

        return view('pages.revision', [
            'page' => $page,
            'content' => $content->load('user'),
            'number' => $ids->search($content->id) + 1,
            'isCurrent' => $ids->last() === $content->id,
        ]);
    }

    public function restore(Request $request, Page $page, Content $content, PageLocker $locker): RedirectResponse
    {
        Gate::authorize('update', $page);

        if ($lock = $locker->heldByOther($page, $request->user())) {
            return back()->with('status', __(':name is editing this page right now; try again shortly.', ['name' => $lock->user->name]));
        }

        if ($page->latestContent?->is($content)) {
            return redirect()->route('pages.show', $page)->with('status', __('That revision is already current.'));
        }

        $page->contents()->make($content->only(['html', 'css', 'js', 'description', 'keywords']))
            ->user()->associate($request->user())
            ->save();

        return redirect()->route('pages.show', $page)->with('status', __('Revision restored.'));
    }
}

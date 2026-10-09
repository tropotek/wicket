<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
}

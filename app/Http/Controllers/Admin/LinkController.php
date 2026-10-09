<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageLink;
use Illuminate\View\View;

class LinkController extends Controller
{
    /** Pages whose current revision links to pages that don't exist, with the missing slugs. */
    public function index(): View
    {
        $broken = PageLink::broken()->orderBy('slug')->get()->groupBy('page_id');
        $pages = Page::whereKey($broken->keys())->orderBy('title')->get();

        return view('admin.links.index', ['pages' => $pages, 'broken' => $broken]);
    }
}

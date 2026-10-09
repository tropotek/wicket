<?php

namespace App\Html\Passes;

use App\Html\HtmlPass;
use App\Html\PageLinks;
use App\Html\RenderContext;
use App\Models\Page;
use App\Models\PageRedirect;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Gate;

/**
 * Links to wiki pages: `wk-page` when the page exists (old slugs rewritten to the current one),
 * `wk-page-new` when it doesn't (yet), `wk-page-locked` when the signed-in viewer can't open it.
 * One lookup per render for all links.
 */
class InternalLinks implements HtmlPass
{
    public function __construct(private PageLinks $links) {}

    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        $anchors = [];
        foreach ($doc->querySelectorAll('a[href]') as $a) {
            if (($slug = $this->links->slug($a->getAttribute('href'))) !== null) {
                $anchors[] = [$a, $slug];
            }
        }

        if ($anchors === []) {
            return;
        }

        $slugs = array_unique(array_column($anchors, 1));
        $pages = Page::whereIn('slug', $slugs)->get()->keyBy('slug');
        $moved = PageRedirect::with('page')->whereIn('slug', array_diff($slugs, $pages->keys()->all()))->get()
            ->filter(fn (PageRedirect $r) => $r->page !== null)
            ->mapWithKeys(fn (PageRedirect $r) => [$r->slug => $r->page]);

        foreach ($anchors as [$a, $slug]) {
            $page = $pages[$slug] ?? $moved[$slug] ?? null;

            if ($page === null) {
                $a->classList->remove('wk-page');
                $a->classList->add('wk-page-new');
                $a->setAttribute('title', __('This page doesn\'t exist yet'));

                continue;
            }

            $a->classList->remove('wk-page-new');
            $a->classList->add('wk-page');

            if ($page->slug !== $slug) {
                $a->setAttribute('href', $this->withSlug($a->getAttribute('href'), $slug, $page->slug));
            }

            if ($context->viewer !== null && Gate::forUser($context->viewer)->denies('view', $page)) {
                $a->classList->add('wk-page-locked');
                $a->setAttribute('title', __('You don\'t have access to this page'));
            }
        }
    }

    /** Swap the slug segment, keeping scheme/host, query and fragment. */
    private function withSlug(string $href, string $old, string $new): string
    {
        return preg_replace('#/'.preg_quote($old, '#').'(?=/?(?:[?\#]|$))#', '/'.$new, $href, 1);
    }
}

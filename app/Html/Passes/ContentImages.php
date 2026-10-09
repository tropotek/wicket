<?php

namespace App\Html\Passes;

use App\Html\HtmlPass;
use App\Html\RenderContext;
use Dom\HTMLDocument;

/**
 * Images get `wk-image` and lazy loading; those not already a link (or marked `no-lightbox`)
 * are wrapped in a link to themselves that the page's lightbox opens.
 */
class ContentImages implements HtmlPass
{
    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        foreach ($doc->querySelectorAll('img[src]') as $img) {
            $img->classList->add('wk-image');
            if (! $img->hasAttribute('loading')) {
                $img->setAttribute('loading', 'lazy');
            }

            if ($img->closest('a') || $img->classList->contains('no-lightbox')) {
                continue;
            }

            $link = $doc->createElement('a');
            $link->setAttribute('href', $img->getAttribute('src'));
            $link->setAttribute('class', 'wk-lightbox');
            $img->replaceWith($link);
            $link->append($img);
        }
    }
}

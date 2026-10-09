<?php

namespace App\Html\Passes;

use App\Html\HtmlPass;
use App\Html\RenderContext;
use App\Support\SiteHosts;
use Dom\HTMLDocument;

/** Links to other sites open in a new tab and are marked wk-link-external. */
class ExternalLinks implements HtmlPass
{
    public function __construct(private SiteHosts $hosts) {}

    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        foreach ($doc->querySelectorAll('a[href]') as $a) {
            $href = trim($a->getAttribute('href'));

            if (! preg_match('#^https?://#i', $href) || $this->hosts->contains(parse_url($href, PHP_URL_HOST), [request()->getHost()])) {
                continue;
            }

            $a->classList->add('wk-link-external');
            $a->setAttribute('target', '_blank');
            $a->setAttribute('rel', 'noopener noreferrer');
        }
    }
}

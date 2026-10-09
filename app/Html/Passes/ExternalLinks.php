<?php

namespace App\Html\Passes;

use App\Html\HtmlPass;
use App\Html\RenderContext;
use Dom\HTMLDocument;

/** Links to other sites open in a new tab and are marked wk-link-external. */
class ExternalLinks implements HtmlPass
{
    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        $ownHost = $this->bareHost(request()->getHost() ?: (string) parse_url(config('app.url'), PHP_URL_HOST));

        foreach ($doc->querySelectorAll('a[href]') as $a) {
            $href = trim($a->getAttribute('href'));

            if (! preg_match('#^https?://#i', $href) || $this->bareHost((string) parse_url($href, PHP_URL_HOST)) === $ownHost) {
                continue;
            }

            $a->classList->add('wk-link-external');
            $a->setAttribute('target', '_blank');
            $a->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function bareHost(string $host): string
    {
        return preg_replace('/^www\./', '', strtolower($host));
    }
}

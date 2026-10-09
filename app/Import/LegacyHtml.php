<?php

namespace App\Import;

use Dom\HTMLDocument;

/**
 * Turns tkwiki's stored HTML into Wicket's: `href="page://Slug"` becomes `href="/<slug>" class="wk-page"`.
 * Runs before the sanitiser, which would drop the unknown page: scheme.
 */
class LegacyHtml
{
    /** @param  array<string, string>  $slugs  legacy slug → Wicket slug, where they differ */
    public function __construct(private array $slugs) {}

    public function rewrite(string $html): string
    {
        if (stripos($html, 'page://') === false) {
            return $html;
        }

        $doc = HTMLDocument::createFromString('<!doctype html><html><body>'.$html, LIBXML_NOERROR, 'UTF-8');

        foreach ($doc->querySelectorAll('a[href]') as $a) {
            $href = $a->getAttribute('href');
            if (! preg_match('#^page://([^?\#]*)(.*)$#i', trim($href), $m)) {
                continue;
            }
            $slug = rawurldecode($m[1]);
            $a->setAttribute('href', '/'.rawurlencode($this->slugs[$slug] ?? $slug).$m[2]);
            $a->classList->add('wk-page');
        }

        return $doc->body->innerHTML;
    }
}

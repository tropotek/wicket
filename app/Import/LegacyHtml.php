<?php

namespace App\Import;

use Dom\HTMLDocument;

/**
 * Turns tkwiki's stored HTML into Wicket's, before the sanitiser (which would drop both legacy forms):
 * - `href="page://Slug"` → `href="/<slug>" class="wk-page"`
 * - `<img class="wk-secret" data-secret-hash="…">` → `<wk-secret data-id="N" contenteditable="false">Name</wk-secret>`,
 *   or the image's title as plain text when the secret wasn't imported.
 */
class LegacyHtml
{
    /**
     * @param  array<string, string>  $slugs  legacy slug → Wicket slug, where they differ
     * @param  array<string, array{id: int, name: string}>  $secrets  legacy placeholder hash → imported secret
     */
    public function __construct(private array $slugs, private array $secrets = []) {}

    public function rewrite(string $html): string
    {
        if (stripos($html, 'page://') === false && stripos($html, 'wk-secret') === false) {
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

        foreach (iterator_to_array($doc->querySelectorAll('img.wk-secret')) as $img) {
            $secret = $this->secrets[strtolower(trim((string) $img->getAttribute('data-secret-hash')))] ?? null;
            if ($secret === null) {
                $img->replaceWith($doc->createTextNode((string) $img->getAttribute('title')));

                continue;
            }
            $el = $doc->createElement('wk-secret');
            $el->setAttribute('data-id', (string) $secret['id']);
            $el->setAttribute('contenteditable', 'false');
            $el->textContent = $secret['name'];
            $img->replaceWith($el);
        }

        return $doc->body->innerHTML;
    }
}

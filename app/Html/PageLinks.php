<?php

namespace App\Html;

use App\Support\ReservedSlugs;
use Dom\HTMLDocument;

/**
 * Recognises links to wiki pages: one-segment paths (`/slug`, with optional query / fragment),
 * root-relative or absolute on this site, whose slug isn't reserved by a route or public file.
 */
class PageLinks
{
    /** Same character set as the catch-all page route. */
    private const SLUG = '[A-Za-z0-9._-]+';

    public function __construct(private ReservedSlugs $reserved) {}

    /** The page slug $href points at, or null when it isn't a page link. */
    public function slug(string $href): ?string
    {
        $href = trim($href);
        $parts = parse_url($href);

        if ($parts === false || isset($parts['user'])) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            if (! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || ! $this->isOwnHost($parts['host'] ?? '')) {
                return null;
            }
        } elseif (! str_starts_with($href, '/') || str_starts_with($href, '//')) {
            return null;
        }

        if (! preg_match('#^/('.self::SLUG.')/?$#', $parts['path'] ?? '', $m) || $this->reserved->contains($m[1])) {
            return null;
        }

        return $m[1];
    }

    /** @return list<string> distinct page slugs linked from $html */
    public function slugsIn(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = HTMLDocument::createFromString('<!doctype html><html><body>'.$html, LIBXML_NOERROR, 'UTF-8');
        $slugs = [];
        foreach ($doc->querySelectorAll('a[href]') as $a) {
            if (($slug = $this->slug($a->getAttribute('href'))) !== null) {
                $slugs[$slug] = true;
            }
        }

        return array_keys($slugs);
    }

    private function isOwnHost(string $host): bool
    {
        $bare = fn (?string $h) => preg_replace('/^www\./', '', strtolower((string) $h));
        $own = array_filter([request()->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);

        return in_array($bare($host), array_map($bare, $own), true);
    }
}

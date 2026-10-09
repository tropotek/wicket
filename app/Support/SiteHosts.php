<?php

namespace App\Support;

/**
 * Hostnames that mean "this wiki": APP_URL's host plus wicket.site_hosts (old or alias names).
 * Compared without port or a leading www.
 */
class SiteHosts
{
    /** @param  list<string>  $extra  more hosts for this check, e.g. the current request's */
    public function contains(?string $host, array $extra = []): bool
    {
        $host = $this->bare($host);

        return $host !== '' && in_array($host, array_map($this->bare(...), [...$this->all(), ...$extra]), true);
    }

    /** $url as a root-relative path when it's an http(s) URL on this site, else unchanged. */
    public function localise(string $url): string
    {
        $parts = parse_url(trim($url));

        if ($parts === false || ! isset($parts['host']) || isset($parts['user'])
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || ! $this->contains($parts['host'])) {
            return $url;
        }

        return (($parts['path'] ?? '') ?: '/')
            .(isset($parts['query']) ? '?'.$parts['query'] : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    /** @return list<string> */
    public function all(): array
    {
        return array_values(array_filter([parse_url((string) config('app.url'), PHP_URL_HOST), ...config('wicket.site_hosts', [])]));
    }

    private function bare(?string $host): string
    {
        return (string) preg_replace('/^www\./', '', strtolower(trim((string) $host)));
    }
}

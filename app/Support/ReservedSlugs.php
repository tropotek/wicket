<?php

namespace App\Support;

use Illuminate\Routing\Router;

/**
 * Slugs a page can't use because a fixed route or a public file already owns
 * that first path segment (the catch-all page route is registered last).
 */
class ReservedSlugs
{
    /** Public paths that may not exist yet: Vite's build output, `storage:link`. */
    private const ALWAYS = ['build', 'storage'];

    /** @var list<string>|null */
    private ?array $all = null;

    public function __construct(private Router $router) {}

    /** @return list<string> */
    public function all(): array
    {
        return $this->all ??= $this->collect();
    }

    /** @return list<string> */
    private function collect(): array
    {
        $fromRoutes = collect($this->router->getRoutes()->getRoutes())
            ->map(fn ($route) => explode('/', trim($route->uri(), '/'))[0])
            ->reject(fn (string $segment) => $segment === '' || str_starts_with($segment, '{'));

        $fromPublic = collect(scandir(public_path()) ?: [])
            ->reject(fn (string $entry) => str_starts_with($entry, '.'));

        return $fromRoutes->merge($fromPublic)->merge(self::ALWAYS)
            ->map(fn (string $s) => strtolower($s))
            ->unique()->values()->all();
    }

    public function contains(string $slug): bool
    {
        return in_array(strtolower($slug), $this->all(), true);
    }
}

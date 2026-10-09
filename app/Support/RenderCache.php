<?php

namespace App\Support;

use App\Models\Content;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches the rendered content of a page for guests. Every write that can change a guest's
 * render (pages, revisions, redirects) bumps one generation number, retiring all entries at once.
 */
class RenderCache
{
    private const GENERATION_KEY = 'wicket:render-generation';

    private const TTL = 86400;

    /** @param  Closure(): string  $render */
    public function forGuest(Content $content, Closure $render): string
    {
        if (! config('wicket.render_cache')) {
            return $render();
        }

        $key = implode(':', ['wicket:render', $this->generation(), $content->id, request()->getHost()]);

        return Cache::remember($key, self::TTL, $render);
    }

    public function flush(): void
    {
        Cache::forever(self::GENERATION_KEY, (int) Cache::get(self::GENERATION_KEY, 0) + 1);
    }

    /** The bump counter, prefixed with the app version so a deploy starts afresh. */
    private function generation(): string
    {
        return config('app.version').'-'.(int) Cache::get(self::GENERATION_KEY, 0);
    }
}

<?php

namespace App\Support;

use App\Models\Page;
use App\Models\PageLock;
use App\Models\User;

/**
 * One editor per page at a time. The page_locks primary key is the arbiter:
 * concurrent acquires insert-or-ignore, and whoever's row landed holds it.
 * A user's lock is shared across their tabs and browsers.
 */
class PageLocker
{
    public function seconds(): int
    {
        return (int) config('wicket.page_lock_seconds');
    }

    /** Take or refresh the lock; null when someone else holds it. */
    public function acquire(Page $page, User $user): ?PageLock
    {
        PageLock::whereKey($page->id)->where('expires_at', '<=', now())->delete();

        PageLock::query()->insertOrIgnore([
            'page_id' => $page->id,
            'user_id' => $user->id,
            'expires_at' => now()->addSeconds($this->seconds()),
            'created_at' => now(),
        ]);

        $lock = PageLock::find($page->id);

        if ($lock === null || $lock->user_id !== $user->id) {
            return null;
        }

        $lock->update(['expires_at' => now()->addSeconds($this->seconds())]);

        return $lock;
    }

    /** The unexpired lock on the page, if any. */
    public function holder(Page $page): ?PageLock
    {
        return PageLock::with('user')->whereKey($page->id)->where('expires_at', '>', now())->first();
    }

    /** The unexpired lock when it belongs to someone other than $user. */
    public function heldByOther(Page $page, User $user): ?PageLock
    {
        $lock = $this->holder($page);

        return $lock && $lock->user_id !== $user->id ? $lock : null;
    }

    public function release(Page $page, User $user): void
    {
        PageLock::whereKey($page->id)->where('user_id', $user->id)->delete();
    }
}

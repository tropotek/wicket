<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An old slug of a page. Written by Page on save; resolved by the pages.show route.
 */
class PageRedirect extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}

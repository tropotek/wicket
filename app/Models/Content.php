<?php

namespace App\Models;

use Database\Factories\ContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A page revision. Revisions are append-only: a save creates a new row.
 */
#[Fillable(['html', 'css', 'js', 'keywords', 'description'])]
class Content extends Model
{
    /** @use HasFactory<ContentFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $attributes = [
        'keywords' => '',
        'description' => '',
    ];

    /** @return BelongsTo<Page, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** The user who saved this revision. @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * author_id and views are deliberately not fillable: set them explicitly.
 */
#[Fillable(['title', 'slug', 'category', 'template', 'title_visible', 'visibility', 'publish'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /** Mirrors the column defaults so a freshly created model has them without a refresh. */
    protected $attributes = [
        'category' => '',
        'template' => '',
        'visibility' => 'public',
        'publish' => true,
        'title_visible' => true,
        'views' => 0,
    ];

    protected function casts(): array
    {
        return [
            'publish' => 'boolean',
            'title_visible' => 'boolean',
            'views' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** All revisions, oldest first. @return HasMany<Content, $this> */
    public function contents(): HasMany
    {
        return $this->hasMany(Content::class)->orderBy('id');
    }

    /** The current revision (highest id). @return HasOne<Content, $this> */
    public function latestContent(): HasOne
    {
        return $this->hasOne(Content::class)->latestOfMany();
    }
}

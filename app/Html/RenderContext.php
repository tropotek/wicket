<?php

namespace App\Html;

use App\Models\Page;
use App\Models\User;

/** What a render is for: the page (if any) and who is looking. */
final readonly class RenderContext
{
    public function __construct(
        public ?Page $page = null,
        public ?User $viewer = null,
    ) {}
}

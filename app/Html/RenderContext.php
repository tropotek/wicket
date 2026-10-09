<?php

namespace App\Html;

use App\Models\Page;
use App\Models\User;

/** What a render is for: the page (if any), who is looking, and the output ('web' or 'pdf'). */
final readonly class RenderContext
{
    public function __construct(
        public ?Page $page = null,
        public ?User $viewer = null,
        public string $target = 'web',
    ) {}

    public function forPdf(): bool
    {
        return $this->target === 'pdf';
    }
}

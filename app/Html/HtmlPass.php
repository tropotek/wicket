<?php

namespace App\Html;

use Dom\HTMLDocument;

/** One step of the render pipeline; mutates the shared document in place. */
interface HtmlPass
{
    public function apply(HTMLDocument $doc, RenderContext $context): void;
}

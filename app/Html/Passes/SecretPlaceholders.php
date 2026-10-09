<?php

namespace App\Html\Passes;

use App\Html\HtmlPass;
use App\Html\RenderContext;
use App\Models\Secret;
use Dom\Element;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;

/**
 * <wk-secret data-id> placeholders become the secret card for viewers allowed to see the secret.
 * Everyone else (and every case where the secret can't be shown) gets a neutral "Hidden" chip:
 * never the name, never a value.
 */
class SecretPlaceholders implements HtmlPass
{
    public function apply(HTMLDocument $doc, RenderContext $context): void
    {
        $placeholders = iterator_to_array($doc->querySelectorAll('wk-secret'));

        if ($placeholders === []) {
            return;
        }

        $ids = array_filter(array_map(fn (Element $el) => ctype_digit($el->getAttribute('data-id') ?? '') ? (int) $el->getAttribute('data-id') : null, $placeholders));
        $secrets = config('wicket.secrets') && $context->viewer && $ids
            ? Secret::whereKey(array_unique($ids))->get()->keyBy('id')
            : collect();

        foreach ($placeholders as $el) {
            $secret = $secrets[(int) $el->getAttribute('data-id')] ?? null;
            $html = $secret && Gate::forUser($context->viewer)->allows('view', $secret)
                ? Blade::render('<x-secret-card :secret="$secret" />', ['secret' => $secret])
                : $this->hidden();

            $scratch = $doc->createElement('div');
            $scratch->innerHTML = $html;
            $el->replaceWith(...iterator_to_array($scratch->childNodes));
        }
    }

    private function hidden(): string
    {
        return '<span class="wk-secret-hidden badge text-bg-secondary fw-normal" title="'.e(__('You can\'t see this secret')).'">'
            .'<i class="fa-solid fa-lock me-1"></i>'.e(__('Hidden')).'</span>';
    }
}

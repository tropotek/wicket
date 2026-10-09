<?php

namespace App\Support;

use App\Models\Page;
use Dom\HTMLDocument;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the pages_fts full-text table in step with pages and their current revision.
 * SQLite only: on other databases every method is a no-op.
 */
class SearchIndex
{
    /** Elements whose text must never be searchable or shown in snippets. */
    private const SKIP = 'wk-secret, script, style, template, noscript';

    public function enabled(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }

    public function sync(Page $page): void
    {
        if (! $this->enabled()) {
            return;
        }

        $content = $page->latestContent()->first();

        DB::transaction(function () use ($page, $content) {
            DB::delete('DELETE FROM pages_fts WHERE rowid = ?', [$page->id]);
            DB::insert('INSERT INTO pages_fts (rowid, title, category, body, meta) VALUES (?, ?, ?, ?, ?)', [
                $page->id,
                $page->title,
                $page->category,
                $this->text((string) $content?->html),
                trim(($content?->description ?? '').' '.($content?->keywords ?? '')),
            ]);
        });
    }

    public function remove(int $pageId): void
    {
        if ($this->enabled()) {
            DB::delete('DELETE FROM pages_fts WHERE rowid = ?', [$pageId]);
        }
    }

    /** Empty the index and refill it from every live page. Returns the number indexed. */
    public function rebuild(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        DB::delete('DELETE FROM pages_fts');
        $count = 0;
        Page::query()->lazyById()->each(function (Page $page) use (&$count) {
            $this->sync($page);
            $count++;
        });

        return $count;
    }

    /** Readable text of stored page HTML, minus secrets and code-ish elements. */
    public function text(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = HTMLDocument::createFromString('<!doctype html><html><body>'.$html, LIBXML_NOERROR, 'UTF-8');
        foreach (iterator_to_array($doc->body->querySelectorAll(self::SKIP)) as $el) {
            $el->remove();
        }
        // Keep words in neighbouring blocks apart ("<p>a</p><p>b</p>" → "a b").
        foreach (iterator_to_array($doc->body->querySelectorAll('p, div, li, td, th, br, h1, h2, h3, h4, h5, h6, pre, blockquote, tr')) as $el) {
            $el->after(' ');
        }

        return trim(preg_replace('/\s+/u', ' ', $doc->body->textContent));
    }
}

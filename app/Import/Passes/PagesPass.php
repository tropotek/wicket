<?php

namespace App\Import\Passes;

use App\Enums\Visibility;
use App\Import\ImportContext;
use App\Import\Pass;
use App\Support\ReservedSlugs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * tkwiki `page` → pages, keeping legacy slugs (`Wiki_How_To`) so old URLs still work. Inserted
 * without model events: the import's closing `wicket:reindex` builds search and links.
 */
class PagesPass implements Pass
{
    /** tkwiki Page::PERM_* → visibility. */
    private const VISIBILITY = [0 => Visibility::Public, 1 => Visibility::Members, 2 => Visibility::Editors, 9 => Visibility::Private];

    public function __construct(private ReservedSlugs $reserved) {}

    public function name(): string
    {
        return 'Pages';
    }

    public function count(ImportContext $context): int
    {
        return $context->source->db()->table('page')->count();
    }

    public function run(ImportContext $context): int
    {
        $taken = [];
        $imported = 0;

        foreach ($context->source->db()->table('page')->orderBy('page_id')->get() as $row) {
            $slug = $this->slug($row, $taken, $context);

            $visibility = self::VISIBILITY[(int) $row->permission] ?? null;
            if ($visibility === null) {
                $visibility = Visibility::Private;
                $context->warn("Page \"{$slug}\" had unknown permission {$row->permission}; imported as private.");
            }

            $id = DB::table('pages')->insertGetId([
                'title' => $row->title !== '' ? $row->title : $slug,
                'slug' => $slug,
                'category' => (string) $row->category,
                'template' => (string) $row->template,
                'visibility' => $visibility->value,
                'author_id' => $context->newId('user', $row->user_id === null ? null : (int) $row->user_id),
                'publish' => (bool) $row->publish,
                'title_visible' => (bool) $row->title_visible,
                'views' => (int) $row->views,
                'created_at' => $row->created ?? Carbon::now(),
                'updated_at' => $row->modified ?? Carbon::now(),
            ]);

            $context->map('page', (int) $row->page_id, $id);
            if ((string) $row->url !== '') {
                $context->slugs[$row->url] = $slug;
            }
            $imported++;
        }

        return $imported;
    }

    /** @param  array<string, true>  $taken  lower-cased slugs already used */
    private function slug(object $row, array &$taken, ImportContext $context): string
    {
        $legacy = (string) $row->url;
        $base = preg_match('/^[A-Za-z0-9._-]+$/', $legacy) ? $legacy : (Str::slug($legacy ?: $row->title) ?: 'page');

        $reserved = $this->reserved->contains($base);
        if ($reserved) {
            $base .= '-page';
        }

        $slug = $base;
        for ($n = 2; isset($taken[strtolower($slug)]); $n++) {
            $slug = $base.'-'.$n;
        }
        $taken[strtolower($slug)] = true;

        if ($slug !== $legacy) {
            $why = $reserved ? ' is reserved by Wicket' : '';
            $context->warn("Page slug \"{$legacy}\"{$why}; imported as \"{$slug}\" (page \"{$row->title}\").");
        }

        return $slug;
    }
}

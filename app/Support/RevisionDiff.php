<?php

namespace App\Support;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\DiffOnlyOutputBuilder;

/**
 * Line diff of two revisions' HTML, as rows for a table.
 *
 * Row shapes: ['type' => 'same'|'added'|'removed', 'text' => string]
 *             ['type' => 'skip', 'count' => int]   (collapsed unchanged lines)
 */
class RevisionDiff
{
    /** Unchanged lines kept either side of a change. */
    private const CONTEXT = 3;

    private const BLOCK_END = '~(</(?:p|div|h[1-6]|li|ul|ol|dl|dt|dd|table|thead|tbody|tfoot|tr|td|th|blockquote|pre|section|article|header|footer|figure|figcaption)>|<br\s*/?>)~i';

    /** @return list<array<string, mixed>> */
    public function rows(string $from, string $to): array
    {
        $diff = (new Differ(new DiffOnlyOutputBuilder))->diffToArray($this->lines($from), $this->lines($to));

        $rows = [];
        foreach ($diff as [$text, $kind]) {
            $type = match ($kind) {
                Differ::OLD => 'same',
                Differ::ADDED => 'added',
                Differ::REMOVED => 'removed',
                default => null, // end-of-line warnings
            };

            if ($type !== null) {
                $rows[] = ['type' => $type, 'text' => rtrim($text, "\r\n")];
            }
        }

        return $this->collapse($rows);
    }

    /**
     * Editor HTML is often a single line; break it after block-level closing
     * tags and <br> so the diff has useful granularity.
     *
     * @return list<string>
     */
    public function lines(string $html): array
    {
        $html = preg_replace(self::BLOCK_END, "$1\n", $html);

        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', $html)),
            fn (string $line) => $line !== '',
        ));
    }

    /** @param  list<array<string, mixed>>  $rows */
    public function hasChanges(array $rows): bool
    {
        foreach ($rows as $row) {
            if (in_array($row['type'], ['added', 'removed'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function collapse(array $rows): array
    {
        $out = [];
        $count = count($rows);

        for ($i = 0; $i < $count;) {
            if ($rows[$i]['type'] !== 'same') {
                $out[] = $rows[$i++];

                continue;
            }

            $start = $i;
            while ($i < $count && $rows[$i]['type'] === 'same') {
                $i++;
            }
            $run = array_slice($rows, $start, $i - $start);

            $keepBefore = $start === 0 ? 0 : self::CONTEXT;      // context after a preceding change
            $keepAfter = $i === $count ? 0 : self::CONTEXT;      // context before a following change

            if (count($run) <= $keepBefore + $keepAfter) {
                array_push($out, ...$run);

                continue;
            }

            array_push($out, ...array_slice($run, 0, $keepBefore));
            $out[] = ['type' => 'skip', 'count' => count($run) - $keepBefore - $keepAfter];
            array_push($out, ...array_slice($run, count($run) - $keepAfter));
        }

        return $out;
    }
}

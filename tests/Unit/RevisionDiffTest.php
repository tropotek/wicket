<?php

namespace Tests\Unit;

use App\Support\RevisionDiff;
use PHPUnit\Framework\TestCase;

class RevisionDiffTest extends TestCase
{
    public function test_marks_added_removed_and_same_lines(): void
    {
        $rows = (new RevisionDiff)->rows('<p>a</p><p>b</p><p>c</p>', '<p>a</p><p>B</p><p>c</p><p>d</p>');

        $this->assertSame([
            ['same', '<p>a</p>'],
            ['removed', '<p>b</p>'],
            ['added', '<p>B</p>'],
            ['same', '<p>c</p>'],
            ['added', '<p>d</p>'],
        ], array_map(fn ($r) => [$r['type'], $r['text']], $rows));
    }

    public function test_splits_after_block_tags_and_br(): void
    {
        $this->assertSame(
            ['<h2>T</h2>', '<p>one<br>', 'two</p>', '<ul><li>x</li>', '<li>y</li>', '</ul>'],
            (new RevisionDiff)->lines('<h2>T</h2><p>one<br>two</p><ul><li>x</li><li>y</li></ul>'),
        );
    }

    public function test_identical_input_has_no_changes(): void
    {
        $diff = new RevisionDiff;

        $this->assertFalse($diff->hasChanges($diff->rows('<p>a</p>', '<p>a</p>')));
        $this->assertTrue($diff->hasChanges($diff->rows('<p>a</p>', '<p>b</p>')));
    }

    public function test_long_unchanged_runs_are_collapsed(): void
    {
        $same = implode('', array_map(fn ($i) => "<p>{$i}</p>", range(1, 20)));
        $rows = (new RevisionDiff)->rows($same.'<p>old</p>', $same.'<p>new</p>');

        $types = array_column($rows, 'type');
        $this->assertSame('skip', $types[0]);
        $this->assertSame(17, $rows[0]['count']);
        $this->assertSame(['same', 'same', 'same', 'removed', 'added'], array_slice($types, 1));
    }
}

<?php

namespace App\Import;

/** One step of the import (users, pages, menu…), run in order by the Importer. */
interface Pass
{
    public function name(): string;

    /** Rows this pass would bring over. */
    public function count(ImportContext $context): int;

    /** Do the work; returns rows imported. Never called on a dry run. */
    public function run(ImportContext $context): int;
}

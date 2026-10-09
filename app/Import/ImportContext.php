<?php

namespace App\Import;

/** Shared state of one import run: options plus legacy-id → new-id maps that later passes read. */
class ImportContext
{
    /** @var array<string, array<int, int>> e.g. ['user' => [legacyId => newId]] */
    public array $ids = [];

    /** @var array<string, string> legacy page url → Wicket slug, for every imported page */
    public array $slugs = [];

    /** @var array<string, array{id: int, name: string}> legacy secret placeholder hash → imported secret */
    public array $secrets = [];

    /** @var list<string> problems worth showing the operator (skipped rows, renamed users…) */
    public array $warnings = [];

    /** @var list<string> summary lines shown after the counts table (e.g. media size) */
    public array $notes = [];

    public function __construct(
        public readonly LegacySource $source,
        public readonly ?string $oldKey = null,
        public readonly ?string $mediaSrc = null,
        public readonly bool $dryRun = false,
    ) {}

    public function map(string $type, int $legacyId, int $newId): void
    {
        $this->ids[$type][$legacyId] = $newId;
    }

    public function newId(string $type, ?int $legacyId): ?int
    {
        return $legacyId === null ? null : ($this->ids[$type][$legacyId] ?? null);
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function note(string $message): void
    {
        $this->notes[] = $message;
    }
}

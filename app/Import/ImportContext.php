<?php

namespace App\Import;

/** Shared state of one import run: options plus legacy-id → new-id maps that later passes read. */
class ImportContext
{
    /** @var array<string, array<int, int>> e.g. ['user' => [legacyId => newId]] */
    public array $ids = [];

    /** @var list<string> problems worth showing the operator (skipped rows, renamed users…) */
    public array $warnings = [];

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
}

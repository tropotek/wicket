<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin-editable settings. Each key's type, default and rules are defined here; stored values
 * (JSON, table `settings`) override the defaults. Read through the Settings facade.
 */
class Settings
{
    private const CACHE_KEY = 'wicket:settings';

    /** Settings mirrored into config on each request (App\Http\Middleware\ApplySettings). */
    public const CONFIG = [
        'site.name' => 'app.name',
        'site.email' => 'mail.from.address',
        'wiki.registration' => 'wicket.registration',
        'wiki.secrets' => 'wicket.secrets',
    ];

    /**
     * @return array<string, array{type: string, rules: array<int, mixed>}>
     */
    public function definitions(): array
    {
        $text = fn (int $max) => ['nullable', 'string', 'max:'.$max];

        return [
            'site.name' => ['type' => 'string', 'rules' => ['required', 'string', 'max:100']],
            'site.email' => ['type' => 'string', 'rules' => ['nullable', 'email', 'max:255']],
            'wiki.home_page' => ['type' => 'int', 'rules' => ['nullable', 'integer', 'exists:pages,id']],
            'wiki.registration' => ['type' => 'bool', 'rules' => ['boolean']],
            'wiki.secrets' => ['type' => 'bool', 'rules' => ['boolean']],
            'meta.description' => ['type' => 'string', 'rules' => $text(255)],
            'meta.keywords' => ['type' => 'string', 'rules' => $text(255)],
            'custom.css' => ['type' => 'string', 'rules' => $text(65535)],
            'custom.js' => ['type' => 'string', 'rules' => $text(65535)],
            'maintenance.enabled' => ['type' => 'bool', 'rules' => ['boolean']],
            'maintenance.message' => ['type' => 'string', 'rules' => $text(2000)],
        ];
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'site.name' => config('app.name'),
            'site.email' => config('mail.from.address'),
            'wiki.home_page' => null,
            'wiki.registration' => (bool) config('wicket.registration'),
            'wiki.secrets' => (bool) config('wicket.secrets'),
            'meta.description' => '',
            'meta.keywords' => '',
            'custom.css' => '',
            'custom.js' => '',
            'maintenance.enabled' => false,
            'maintenance.message' => '',
        ];
    }

    /** @return array<string, mixed> every setting, stored values over defaults */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')
            ->map(fn (?string $json) => json_decode((string) $json, true))
            ->all());

        return array_merge($this->defaults(), array_intersect_key($stored, $this->defaults()));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** Store values (unknown keys ignored), cast by definition. */
    public function set(array $values): void
    {
        $definitions = $this->definitions();

        foreach (array_intersect_key($values, $definitions) as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => json_encode($this->cast($definitions[$key]['type'], $value)), 'updated_at' => now()],
            );
        }

        Cache::forget(self::CACHE_KEY);
    }

    private function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => $value === null || $value === '' ? null : (int) $value,
            default => (string) ($value ?? ''),
        };
    }
}

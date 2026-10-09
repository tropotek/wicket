<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthStatus
{
    public const HEARTBEAT_KEY = 'scheduler.last_run';

    private static ?int $startedAt = null;

    public function __construct(private ?string $versionFile = null)
    {
        self::$startedAt ??= time();
    }

    public function version(): string
    {
        $file = $this->versionFile ?? base_path('version.md');
        $v = is_readable($file) ? trim((string) file_get_contents($file)) : '';

        return $v !== '' ? $v : 'unknown';
    }

    /** Seconds since this PHP process/Octane worker first built the service. */
    public function uptime(): int
    {
        return time() - self::$startedAt;
    }

    public function db(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function cache(): bool
    {
        try {
            $key = 'health.probe';
            Cache::put($key, 1, 10);

            return Cache::get($key) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    public function scheduler(): ?string
    {
        try {
            $ts = Cache::get(self::HEARTBEAT_KEY);
        } catch (Throwable) {
            return null;
        }

        return $ts ? Carbon::createFromTimestampUTC((int) $ts)->toIso8601String() : null;
    }
}

<?php

namespace Tests\Feature;

use App\Support\HealthStatus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_up_returns_expected_keys_when_healthy(): void
    {
        $this->getJson('/up')
            ->assertOk()
            ->assertJsonStructure(['version', 'uptime', 'db', 'cache', 'scheduler'])
            ->assertJsonPath('db', 'ok')
            ->assertJsonPath('cache', 'ok')
            ->assertJsonPath('scheduler', null);
    }

    public function test_up_is_json_without_accept_header(): void
    {
        $this->get('/up')->assertOk()->assertHeader('Content-Type', 'application/json');
    }

    public function test_up_returns_503_when_db_down(): void
    {
        config([
            'database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/dir/x.sqlite', 'prefix' => ''],
            'database.default' => 'broken',
        ]);
        app('db')->purge();

        $this->getJson('/up')
            ->assertStatus(503)
            ->assertJsonPath('db', 'error')
            ->assertJsonStructure(['version', 'uptime', 'db', 'cache', 'scheduler']);
    }

    public function test_up_reports_cache_error_but_stays_200(): void
    {
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('down'));
        Cache::shouldReceive('get')->andReturn(null);

        $this->getJson('/up')->assertOk()->assertJsonPath('cache', 'error');
    }

    public function test_up_reports_last_scheduler_run(): void
    {
        Cache::forever(HealthStatus::HEARTBEAT_KEY, 1760000000);

        $this->getJson('/up')->assertOk()->assertJsonPath('scheduler', '2025-10-09T08:53:20+00:00');
    }

    public function test_version_falls_back_when_file_missing(): void
    {
        $this->assertSame('unknown', (new HealthStatus(base_path('nope.md')))->version());
    }

    public function test_up_sets_no_cookies(): void
    {
        $this->getJson('/up')->assertCookieMissing(config('session.cookie'));
    }
}

<?php

namespace Tests\Feature\Import;

use App\Import\LegacySource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\LegacyFixture;
use Tests\TestCase;

class ImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private LegacyFixture $legacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->legacy = new LegacyFixture;
        $userId = $this->legacy->insert('user', ['type' => 'staff', 'given_name' => 'Sam']);
        $this->legacy->insert('auth', ['fkey' => 'App\Db\User', 'fid' => $userId, 'username' => 'sam', 'email' => 'sam@example.org']);
        $pageId = $this->legacy->insert('page', ['user_id' => $userId, 'title' => 'Home', 'url' => 'home']);
        $this->legacy->insert('content', ['page_id' => $pageId, 'user_id' => $userId, 'html' => '<p>Hi</p>']);
    }

    protected function tearDown(): void
    {
        $this->legacy->drop();

        parent::tearDown();
    }

    public function test_dsn_parsing(): void
    {
        $mysql = LegacySource::config('mysql://wiki%40ops:p%40ss%3Aword@db.example:3307/old_wiki');
        $this->assertSame(['mysql', 'db.example', 3307, 'old_wiki', 'wiki@ops', 'p@ss:word'],
            [$mysql['driver'], $mysql['host'], $mysql['port'], $mysql['database'], $mysql['username'], $mysql['password']]);

        $this->assertSame(['sqlite', '/tmp/x.sqlite'], array_values(array_intersect_key(LegacySource::config('sqlite:///tmp/x.sqlite'), ['driver' => 1, 'database' => 1])));

        $this->expectException(\InvalidArgumentException::class);
        LegacySource::config('postgres://x@y/z');
    }

    public function test_dry_run_reports_and_writes_nothing(): void
    {
        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--dry-run' => true])
            ->expectsTable(['Table', 'Rows'], [['auth', 1], ['user', 1], ['page', 1], ['content', 1], ['links', 0], ['menu_item', 0], ['secret', 0], ['registry', 0]])
            ->expectsOutputToContain('Dry run: nothing was changed.')
            ->assertSuccessful();

        $this->assertSame(0, User::count());
    }

    public function test_bad_sources_fail_cleanly(): void
    {
        $this->artisan('wicket:import')->expectsOutputToContain('--from=')->assertFailed();
        $this->artisan('wicket:import', ['--from' => 'ftp://nope'])->expectsOutputToContain('mysql://')->assertFailed();

        $empty = storage_path('framework/testing/empty-'.uniqid().'.sqlite');
        touch($empty);
        $this->artisan('wicket:import', ['--from' => 'sqlite://'.$empty])->expectsOutputToContain('missing tables')->assertFailed();
        unlink($empty);
    }

    public function test_refuses_a_non_empty_target_without_force(): void
    {
        User::factory()->create();

        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--no-interaction' => true])
            ->expectsOutputToContain('--force')
            ->assertFailed();

        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Emptied existing')
            ->assertSuccessful();
        $this->assertSame(0, User::count(), 'no passes yet, so the wipe leaves nothing');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = base_path('data/backups');
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();

        parent::tearDown();
    }

    private function clean(): void
    {
        foreach (glob(base_path('data/backups/wiki-*.sqlite')) ?: [] as $f) {
            unlink($f);
        }
        @rmdir(base_path('data/backups'));
    }

    public function test_writes_a_readable_snapshot(): void
    {
        Page::factory()->create(['title' => 'Snapshot Me']);

        $this->artisan('wicket:backup')->expectsOutputToContain('Snapshot written')->assertSuccessful();

        $files = glob($this->dir.'/wiki-*.sqlite');
        $this->assertCount(1, $files);
        $this->assertSame('600', substr(sprintf('%o', fileperms($files[0])), -3));

        Config::set('database.connections.snapshot', ['driver' => 'sqlite', 'database' => $files[0]]);
        $this->assertSame('Snapshot Me', DB::connection('snapshot')->table('pages')->value('title'));
    }

    public function test_keeps_only_the_newest(): void
    {
        foreach (range(1, 3) as $i) {
            $this->travel(1)->seconds();
            $this->artisan('wicket:backup', ['--keep' => 2])->assertSuccessful();
        }

        $this->assertCount(2, glob($this->dir.'/wiki-*.sqlite'));
    }
}

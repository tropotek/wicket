<?php

namespace Tests\Feature\Import;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\PendingCommand;
use Tests\Support\LegacyFixture;
use Tests\TestCase;

class ImportMediaTest extends TestCase
{
    use RefreshDatabase;

    private LegacyFixture $legacy;

    private string $src;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->legacy = new LegacyFixture;
        $userId = $this->legacy->insert('user', ['type' => 'staff', 'given_name' => 'Sam']);
        $this->legacy->insert('auth', ['fkey' => 'App\Db\User', 'fid' => $userId, 'username' => 'sam', 'email' => 'sam@example.org']);

        $this->src = storage_path('framework/testing/legacy-media-'.uniqid());
        $this->file('logo.png', 'png-bytes', 1_600_000_000);
        $this->file('Mick/personal/My Docs/Rental 2022.pdf', str_repeat('x', 2048), 1_650_000_000);
        $this->file('.tmb/l1_bG9nby5wbmc.png', 'thumb');
        $this->file('.trash/old.pdf', 'gone');
        $this->file('Mick/.hidden.txt', 'secret');
        $this->file('shell.php', '<?php');
        symlink('/etc/passwd', $this->src.'/passwd.txt');
    }

    protected function tearDown(): void
    {
        $this->legacy->drop();
        File::deleteDirectory($this->src);

        parent::tearDown();
    }

    private function file(string $path, string $contents, ?int $mtime = null): void
    {
        File::ensureDirectoryExists(dirname($this->src.'/'.$path));
        File::put($this->src.'/'.$path, $contents);
        $mtime && touch($this->src.'/'.$path, $mtime);
    }

    private function import(array $options = []): PendingCommand
    {
        return $this->artisan('wicket:import', $options + ['--from' => $this->legacy->dsn(), '--media-src' => $this->src, '--no-interaction' => true]);
    }

    public function test_copies_the_tree_keeping_paths_and_mtimes(): void
    {
        $this->import()
            ->expectsOutputToContain('Media: 2 files, 2.0 KB')
            ->assertSuccessful()->run();

        $disk = Storage::disk('media');
        $this->assertSame('png-bytes', $disk->get('logo.png'));
        $this->assertSame(1_600_000_000, $disk->lastModified('logo.png'));
        $this->assertSame(2048, $disk->size('Mick/personal/My Docs/Rental 2022.pdf'));
        $this->assertSame(1_650_000_000, $disk->lastModified('Mick/personal/My Docs/Rental 2022.pdf'));
    }

    public function test_skips_internal_linked_and_blocked_files(): void
    {
        $this->import()
            ->expectsOutputToContain('shell.php')
            ->expectsOutputToContain('passwd.txt')
            ->assertSuccessful()->run();

        $disk = Storage::disk('media');
        foreach (['.tmb', '.trash', 'Mick/.hidden.txt', 'shell.php', 'passwd.txt'] as $path) {
            $this->assertFalse($disk->exists($path), $path);
        }
    }

    public function test_dry_run_reports_without_copying(): void
    {
        $this->import(['--dry-run' => true])
            ->expectsOutputToContain('Media: 2 files, 2.0 KB')
            ->assertSuccessful()->run();

        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_missing_source_fails_before_writing(): void
    {
        $this->import(['--media-src' => $this->src.'/nope'])
            ->expectsOutputToContain('--media-src')
            ->assertFailed()->run();

        $this->assertSame(0, User::count());
    }

    public function test_no_source_copies_nothing(): void
    {
        $this->artisan('wicket:import', ['--from' => $this->legacy->dsn(), '--no-interaction' => true])
            ->doesntExpectOutputToContain('Media:')
            ->assertSuccessful()->run();

        $this->assertSame([], Storage::disk('media')->allFiles());
    }
}

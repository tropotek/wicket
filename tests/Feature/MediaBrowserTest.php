<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaBrowserTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $disk;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('media', ['links' => 'skip']);
        $this->editor = User::factory()->editor()->create();
    }

    private function htmx(): static
    {
        return $this->actingAs($this->editor)->withHeaders(['HX-Request' => 'true']);
    }

    private function png(string $name, int $w = 800, int $h = 600): UploadedFile
    {
        return UploadedFile::fake()->image($name, $w, $h);
    }

    public function test_access(): void
    {
        $this->get('/media')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/media')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/media/folders', ['name' => 'x'])->assertForbidden();
        $this->actingAs($this->editor)->get('/media')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/media')->assertOk();
    }

    public function test_listing_shows_folders_then_files_and_hides_dot_entries(): void
    {
        $this->disk->put('zeta.txt', 'z');
        $this->disk->put('Alpha.pdf', 'a');
        $this->disk->makeDirectory('photos');
        $this->disk->makeDirectory('.thumbs');
        $this->disk->makeDirectory('.trash');
        $this->disk->put('.hidden.txt', 'h');

        $this->actingAs($this->editor)->get('/media')
            ->assertOk()
            ->assertSeeInOrder(['photos', 'Alpha.pdf', 'zeta.txt'])
            ->assertDontSee('.thumbs')
            ->assertDontSee('.trash')
            ->assertDontSee('.hidden.txt');
    }

    public function test_folder_navigation_has_breadcrumbs_and_htmx_partial(): void
    {
        $this->disk->put('docs/2026/report.pdf', 'r');

        $this->actingAs($this->editor)->get('/media?dir=docs/2026')
            ->assertOk()
            ->assertSeeInOrder(['breadcrumb', 'docs', '2026', 'report.pdf'], false);

        $this->htmx()->withHeaders(['HX-Target' => 'media-browser'])->get('/media?dir=docs')
            ->assertOk()
            ->assertSee('id="media-browser"', false)
            ->assertDontSee('<html', false);
    }

    public function test_traversal_and_hidden_paths_are_404_everywhere(): void
    {
        $this->disk->put('.thumbs/x.png', 'x');
        $this->disk->put('ok.txt', 'ok');
        $outside = sys_get_temp_dir().'/wicket-outside-'.uniqid();
        mkdir($outside);
        file_put_contents($outside.'/secret.txt', 's');
        symlink($outside, $this->disk->path('escape'));

        $bad = ['..', '../', 'a/../..', '/etc', '.thumbs', '.thumbs/x.png', "ok\0.txt", 'a\\b', 'escape', 'escape/secret.txt', '%2e%2e'];

        foreach ($bad as $path) {
            $this->actingAs($this->editor)->get('/media?dir='.urlencode($path))->assertNotFound();
            $this->htmx()->patch('/media/rename', ['path' => $path, 'name' => 'x'])->assertNotFound();
            $this->htmx()->delete('/media', ['path' => $path])->assertNotFound();
            $this->htmx()->post('/media/folders', ['dir' => $path, 'name' => 'x'])->assertNotFound();
            $this->actingAs($this->editor)->get('/media/thumb?path='.urlencode($path))->assertNotFound();
        }

        $this->assertFileExists($outside.'/secret.txt');
        unlink($this->disk->path('escape'));
        unlink($outside.'/secret.txt');
        rmdir($outside);
    }

    public function test_root_cannot_be_renamed_or_deleted(): void
    {
        $this->htmx()->delete('/media', ['path' => ''])->assertNotFound();
        $this->htmx()->patch('/media/rename', ['path' => '/', 'name' => 'x'])->assertNotFound();
    }

    public function test_upload_multiple_files(): void
    {
        $this->disk->makeDirectory('docs');
        $this->disk->put('docs/my-file.pdf', 'old');

        $this->htmx()->post('/media/files', [
            'dir' => 'docs',
            'files' => [
                UploadedFile::fake()->create('My File.PDF', 5, 'application/pdf'),
                $this->png('Photo One.png'),
            ],
        ])->assertOk()->assertSee('2 files uploaded.');

        $this->assertTrue($this->disk->exists('docs/my-file-2.pdf'));
        $this->assertTrue($this->disk->exists('docs/photo-one.png'));
        $this->assertTrue($this->disk->exists('.thumbs/docs/photo-one.png'));
        $this->assertFalse($this->disk->exists('.thumbs/docs/my-file-2.pdf'));
    }

    public function test_upload_rejects_disallowed_and_oversize_files(): void
    {
        config(['wicket.upload_max_kb' => 50]);

        foreach (['x.svg', 'x.php', 'x.html', 'x.js', 'x.phtml', 'x'] as $name) {
            $this->htmx()->post('/media/files', ['files' => [UploadedFile::fake()->create($name, 1)]])
                ->assertStatus(422)
                ->assertSee($name);
        }
        $this->htmx()->post('/media/files', ['files' => [UploadedFile::fake()->create('big.pdf', 60)]])->assertStatus(422);
        $this->htmx()->post('/media/files', [])->assertStatus(422);

        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_unreadable_image_is_rejected_and_removed(): void
    {
        $this->htmx()->post('/media/files', ['files' => [UploadedFile::fake()->createWithContent('fake.png', 'not an image')]])
            ->assertStatus(422)
            ->assertSee('could not be read as an image');

        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_make_folder(): void
    {
        $this->htmx()->post('/media/folders', ['dir' => '', 'name' => 'Team Photos'])->assertOk()->assertSee('team-photos');
        $this->assertTrue(is_dir($this->disk->path('team-photos')));

        $this->htmx()->post('/media/folders', ['dir' => '', 'name' => 'team photos'])->assertStatus(422)->assertSee('already exists');
        $this->htmx()->post('/media/folders', ['dir' => '', 'name' => ''])->assertStatus(422);
        $this->htmx()->post('/media/folders', ['dir' => 'missing', 'name' => 'x'])->assertNotFound();
    }

    public function test_rename_file_keeps_extension_and_moves_thumbnail(): void
    {
        $this->htmx()->post('/media/files', ['dir' => '', 'files' => [$this->png('old.png')]]);

        $this->htmx()->patch('/media/rename', ['path' => 'old.png', 'name' => 'New Name.png'])->assertOk();
        $this->assertTrue($this->disk->exists('new-name.png'));
        $this->assertTrue($this->disk->exists('.thumbs/new-name.png'));
        $this->assertFalse($this->disk->exists('old.png'));
        $this->assertFalse($this->disk->exists('.thumbs/old.png'));

        // A different extension is just part of the name.
        $this->htmx()->patch('/media/rename', ['path' => 'new-name.png', 'name' => 'evil.php'])->assertOk();
        $this->assertTrue($this->disk->exists('evilphp.png'));
    }

    public function test_rename_folder_and_collision(): void
    {
        $this->disk->put('a/one.txt', '1');
        $this->disk->put('.thumbs/a/pic.png', 't');
        $this->disk->makeDirectory('b');

        $this->htmx()->patch('/media/rename', ['path' => 'a', 'name' => 'b'])->assertStatus(422)->assertSee('already exists');
        $this->htmx()->patch('/media/rename', ['path' => 'a', 'name' => 'Archive'])->assertOk();

        $this->assertTrue($this->disk->exists('archive/one.txt'));
        $this->assertTrue($this->disk->exists('.thumbs/archive/pic.png'));
    }

    public function test_delete_file_and_folder(): void
    {
        $this->htmx()->post('/media/files', ['dir' => '', 'files' => [$this->png('pic.png')]]);
        $this->disk->put('docs/a.txt', 'a');
        $this->disk->put('docs/sub/b.txt', 'b');

        $this->actingAs($this->editor)->get('/media')->assertSee('Delete folder &quot;docs&quot; and the 2 files in it?', false);

        $this->htmx()->delete('/media', ['path' => 'pic.png'])->assertOk()->assertSee('pic.png deleted.');
        $this->htmx()->delete('/media', ['path' => 'docs'])->assertOk();

        $this->assertSame([], $this->disk->allFiles());
        $this->assertSame([], array_values(array_diff($this->disk->allDirectories(), ['.thumbs'])));
    }

    public function test_thumb_is_generated_for_files_without_one(): void
    {
        $source = $this->png('legacy.png', 1000, 500);
        $this->disk->put('legacy.png', file_get_contents($source->getPathname()));

        $this->actingAs($this->editor)->get('/media/thumb?path=legacy.png')->assertOk();

        [$w, $h] = getimagesize($this->disk->path('.thumbs/legacy.png'));
        $this->assertSame([400, 200], [$w, $h]);

        $this->disk->put('notes.txt', 'n');
        $this->actingAs($this->editor)->get('/media/thumb?path=notes.txt')->assertNotFound();
    }

    public function test_picker_mode_uses_bare_layout_and_select_buttons(): void
    {
        $this->disk->put('pic.png', 'p');
        $this->disk->put('doc.pdf', 'd');

        $this->actingAs($this->editor)->get('/media?picker=image')
            ->assertOk()
            ->assertDontSee('navbar', false)
            ->assertSee('wicketMediaPick', false)
            ->assertSee('/data/media/pic.png')
            ->assertDontSee("'\\/data\\/media\\/doc.pdf'", false);

        $this->actingAs($this->editor)->get('/media?picker=file')
            ->assertSee("'\\/data\\/media\\/doc.pdf'", false);

        $this->actingAs($this->editor)->get('/media')->assertDontSee('wicketMediaPick', false);
    }

    public function test_media_picker_offers_video_and_audio_only(): void
    {
        $this->disk->put('clip.mp4', 'v');
        $this->disk->put('song.mp3', 'a');
        $this->disk->put('pic.png', 'p');

        $this->actingAs($this->editor)->get('/media?picker=media')
            ->assertOk()
            ->assertSee("'\\/data\\/media\\/clip.mp4'", false)
            ->assertSee("'\\/data\\/media\\/song.mp3'", false)
            ->assertDontSee("'\\/data\\/media\\/pic.png'", false);
    }

    public function test_non_htmx_writes_redirect_to_the_folder(): void
    {
        $this->disk->makeDirectory('docs');

        $this->actingAs($this->editor)->post('/media/folders', ['dir' => 'docs', 'name' => 'Sub'])
            ->assertRedirect(route('media.index', ['dir' => 'docs']));
    }
}

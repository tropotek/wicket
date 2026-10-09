<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
        $this->travelTo(now()->setDate(2026, 3, 7));
    }

    private function upload(?User $user, ?UploadedFile $file)
    {
        if ($user) {
            $this->actingAs($user);
        }

        return $this->postJson('/media/upload', $file ? ['file' => $file] : []);
    }

    public function test_guests_and_members_cannot_upload(): void
    {
        $this->upload(null, UploadedFile::fake()->image('a.png'))->assertUnauthorized();
        $this->upload(User::factory()->create(), UploadedFile::fake()->image('a.png'))->assertForbidden();

        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_editor_upload_stores_original_and_thumbnail(): void
    {
        $file = UploadedFile::fake()->image('Holiday Photo.JPG', 1600, 1200);

        $this->upload(User::factory()->editor()->create(), $file)
            ->assertOk()
            ->assertExactJson([
                'location' => '/data/media/uploads/2026/03/holiday-photo.jpg',
                'thumbnail' => '/data/media/.thumbs/uploads/2026/03/holiday-photo.jpg',
                'name' => 'holiday-photo.jpg',
            ]);

        $disk = Storage::disk('media');
        $this->assertSame(file_get_contents($file->getPathname()), $disk->get('uploads/2026/03/holiday-photo.jpg'));
        [$w, $h] = getimagesize($disk->path('.thumbs/uploads/2026/03/holiday-photo.jpg'));
        $this->assertSame([400, 300], [$w, $h]);
    }

    public function test_names_get_a_suffix_on_collision(): void
    {
        $editor = User::factory()->editor()->create();

        $this->upload($editor, UploadedFile::fake()->image('image.png'))->assertJsonPath('name', 'image.png');
        $this->upload($editor, UploadedFile::fake()->image('image.png'))->assertJsonPath('name', 'image-2.png');
        $this->upload($editor, UploadedFile::fake()->image('image.png'))->assertJsonPath('name', 'image-3.png');
    }

    public function test_small_images_are_not_upscaled(): void
    {
        $this->upload(User::factory()->editor()->create(), UploadedFile::fake()->image('tiny.gif', 50, 20))->assertOk();

        [$w, $h] = getimagesize(Storage::disk('media')->path('.thumbs/uploads/2026/03/tiny.gif'));
        $this->assertSame([50, 20], [$w, $h]);
    }

    public function test_unnamed_files_fall_back_to_image(): void
    {
        $this->upload(User::factory()->editor()->create(), UploadedFile::fake()->image('---.webp'))
            ->assertJsonPath('name', 'image.webp');
    }

    public function test_rejects_bad_files(): void
    {
        $editor = User::factory()->editor()->create();
        config(['wicket.upload_max_kb' => 100]);

        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->upload($editor, $svg)->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload($editor, UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))->assertUnprocessable();
        $this->upload($editor, UploadedFile::fake()->image('big.png')->size(200))->assertUnprocessable();
        $this->upload($editor, null)->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('media')->allFiles());
    }
}

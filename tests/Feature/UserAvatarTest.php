<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    private Filesystem $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('media');
    }

    /** A solid-colour PNG; distinct colours give distinct stored files. */
    private function photo(string $name, int $red): UploadedFile
    {
        $image = imagecreatetruecolor(300, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, $red, 80, 120));
        ob_start();
        imagepng($image);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    private function saveProfile(User $user, array $extra = [])
    {
        return $this->actingAs($user)->patch('/profile', $extra + ['name' => $user->name, 'email' => $user->email]);
    }

    public function test_upload_stores_a_square_webp_and_the_nav_shows_it(): void
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $this->actingAs($user)->get('/profile')->assertSee('<span class="wk-avatar" aria-hidden="true">AL</span>', false);

        $this->saveProfile($user, ['avatar' => UploadedFile::fake()->image('me.jpg', 800, 600)])
            ->assertSessionHasNoErrors()->assertRedirect('/profile');

        $path = $user->refresh()->avatar;
        $this->assertMatchesRegularExpression('#^\.avatars/[0-9a-f]{16}\.webp$#', $path);
        $this->disk->assertExists($path);
        $this->assertSame([256, 256], array_slice(getimagesize($this->disk->path($path)), 0, 2));
        $this->assertSame('image/webp', getimagesize($this->disk->path($path))['mime']);

        $this->actingAs($user)->get('/profile')
            ->assertSee('<img src="/data/media/'.$path.'" class="wk-avatar" alt="">', false)
            ->assertDontSee('<span class="wk-avatar" aria-hidden="true">AL</span>', false);
    }

    public function test_replace_deletes_the_old_file_and_plain_saves_keep_it(): void
    {
        $user = User::factory()->create();
        $this->saveProfile($user, ['avatar' => $this->photo('a.png', 10)]);
        $old = $user->refresh()->avatar;

        $this->saveProfile($user, ['avatar' => $this->photo('b.png', 200)]);
        $new = $user->refresh()->avatar;
        $this->assertNotSame($old, $new);
        $this->disk->assertMissing($old);

        $this->saveProfile($user, ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame($new, $user->refresh()->avatar);
        $this->disk->assertExists($new);
    }

    public function test_remove_clears_the_photo(): void
    {
        $user = User::factory()->create();
        $this->saveProfile($user, ['avatar' => UploadedFile::fake()->image('a.png', 300, 300)]);
        $path = $user->refresh()->avatar;

        $this->saveProfile($user, ['avatar_remove' => '1'])->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->avatar);
        $this->disk->assertMissing($path);
    }

    public function test_rejects_non_images_and_large_files(): void
    {
        $user = User::factory()->create();

        $this->saveProfile($user, ['avatar' => UploadedFile::fake()->create('me.pdf', 10, 'application/pdf')])->assertSessionHasErrors('avatar');
        $svg = UploadedFile::fake()->createWithContent('me.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->saveProfile($user, ['avatar' => $svg])->assertSessionHasErrors('avatar');
        $this->saveProfile($user, ['avatar' => UploadedFile::fake()->image('big.jpg')->size(5000)])->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar);
        $this->assertSame([], $this->disk->allFiles());
    }

    public function test_admin_edit_shows_the_photo_and_can_remove_it(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['username' => 'bob']);
        $this->saveProfile($user, ['avatar' => UploadedFile::fake()->image('a.png', 300, 300)]);
        $path = $user->refresh()->avatar;

        $this->actingAs($admin)->get(route('admin.users.edit', $user))->assertOk()
            ->assertSee('/data/media/'.$path, false)
            ->assertSee('name="avatar_remove"', false);

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'username' => 'bob', 'name' => $user->name, 'email' => $user->email,
            'role' => $user->role->value, 'active' => '1', 'avatar_remove' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->refresh()->avatar);
        $this->disk->assertMissing($path);
    }

    public function test_admin_save_without_remove_keeps_the_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['username' => 'bob']);
        $this->saveProfile($user, ['avatar' => UploadedFile::fake()->image('a.png', 300, 300)]);
        $path = $user->refresh()->avatar;

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'username' => 'bob', 'name' => 'Bob B', 'email' => $user->email,
            'role' => $user->role->value, 'active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame($path, $user->refresh()->avatar);
    }
}

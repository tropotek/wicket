<?php

namespace Tests\Feature;

use App\Facades\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MovePublicDataMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        $migration = require database_path('migrations/2026_10_10_020000_move_site_images_to_data_public.php');
        $migration->up();
    }

    public function test_moves_the_logo_and_photos_out_of_media(): void
    {
        config(['cache.default' => 'array']);
        $media = Storage::fake('media');
        $public = Storage::fake('public_data');

        $media->put('.site/logo-abc.png', 'logo');
        $media->put('.avatars/111.webp', 'one');
        $media->put('Docs/manual.pdf', 'user upload');
        Settings::set(['theme.logo' => '.site/logo-abc.png']);
        $with = User::factory()->create();
        $with->forceFill(['avatar' => '.avatars/111.webp'])->save();
        $missing = User::factory()->create();
        $missing->forceFill(['avatar' => '.avatars/gone.webp'])->save();
        $without = User::factory()->create();

        $this->migrate();

        $this->assertSame('site/logo-abc.png', Settings::get('theme.logo'));
        $this->assertSame('logo', $public->get('site/logo-abc.png'));
        $this->assertSame('avatars/111.webp', $with->refresh()->avatar);
        $this->assertSame('one', $public->get('avatars/111.webp'));
        $this->assertNull($missing->refresh()->avatar);
        $this->assertNull($without->refresh()->avatar);

        $this->assertSame(['Docs/manual.pdf'], $media->allFiles());
        $this->assertSame(['Docs'], $media->directories());
    }

    public function test_nothing_to_move_is_fine(): void
    {
        Storage::fake('media');
        Storage::fake('public_data');

        $this->migrate();

        $this->assertSame('', Settings::get('theme.logo'));
    }
}

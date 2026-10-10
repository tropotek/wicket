<?php

use App\Facades\Settings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Moves the site logo and profile photos from data/media/.site/ and data/media/.avatars/ to
 * data/public/site/ and data/public/avatars/, rewriting the stored paths. A photo whose file is
 * missing is cleared. One way: the old layout isn't restored.
 */
return new class extends Migration
{
    private const MOVES = ['.site/' => 'site/', '.avatars/' => 'avatars/'];

    /** @var array<string, true> old folders a file was moved out of */
    private array $emptied = [];

    public function up(): void
    {
        $logo = (string) Settings::get('theme.logo', '');
        if ($logo !== '') {
            Settings::set(['theme.logo' => $this->move($logo) ?? '']);
        }

        DB::table('users')->whereNotNull('avatar')->orderBy('id')->each(function (object $user) {
            $path = $this->move($user->avatar);
            if ($path !== $user->avatar) {
                DB::table('users')->where('id', $user->id)->update(['avatar' => $path]);
            }
        });

        $media = Storage::disk('media');
        foreach (array_keys($this->emptied) as $dir) {
            if ($media->allFiles($dir) === []) {
                $media->deleteDirectory($dir);
            }
        }
    }

    public function down(): void {}

    /** New path for an old-layout path (file moved), the path unchanged if already new, or null if its file is gone. */
    private function move(string $path): ?string
    {
        foreach (self::MOVES as $old => $new) {
            if (! str_starts_with($path, $old)) {
                continue;
            }
            $media = Storage::disk('media');
            if (! $media->exists($path)) {
                return null;
            }
            $target = $new.substr($path, strlen($old));
            Storage::disk('public_data')->writeStream($target, $media->readStream($path));
            $media->delete($path);
            $this->emptied[rtrim($old, '/')] = true;

            return $target;
        }

        return $path;
    }
};

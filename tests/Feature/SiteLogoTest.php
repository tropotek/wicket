<?php

namespace Tests\Feature;

use App\Facades\Settings;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteLogoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Filesystem $disk;

    private Filesystem $media;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->disk = Storage::fake('public_data');
        $this->media = Storage::fake('media');
        $this->admin = User::factory()->admin()->create();
    }

    private function save(array $overrides = [])
    {
        return $this->actingAs($this->admin)->put('/admin/settings', $overrides + [
            'site__name' => 'Team Wiki',
            'site__email' => 'wiki@example.org',
            'wiki__home_page' => '',
            'wiki__registration' => '0',
            'wiki__secrets' => '1',
            'wiki__backlinks' => '1',
            'wiki__contact' => '1',
            'meta__description' => '',
            'meta__keywords' => '',
            'custom__css' => '',
            'custom__js' => '',
            'maintenance__enabled' => '0',
            'maintenance__message' => '',
            'theme__accent' => '#0d6efd',
            'theme__radius' => 'default',
            'theme__font' => 'system',
            'theme__mode' => 'system',
            'theme__logo_text' => '1',
        ]);
    }

    public function test_upload_stores_the_logo_and_the_nav_shows_it(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('Logo.PNG', 120, 40)])->assertSessionHasNoErrors();

        $path = Settings::get('theme.logo');
        $this->assertMatchesRegularExpression('#^site/logo-[0-9a-f]{12}\.png$#', $path);
        $this->disk->assertExists($path);
        $this->assertSame([], $this->media->allFiles(), 'nothing lands in the media library');

        $this->get('/')->assertOk()
            ->assertSee('<img src="/data/public/'.$path.'" class="wk-logo" alt="">', false)
            ->assertSeeInOrder(['wk-logo', 'Team Wiki']);
        $this->actingAs($this->admin)->get('/admin/settings')->assertSee('/data/public/'.$path, false);
    }

    public function test_replacing_deletes_the_old_file_and_saving_other_settings_keeps_it(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('a.png', 50, 50)]);
        $old = Settings::get('theme.logo');

        $this->save(['logo' => UploadedFile::fake()->image('b.jpg', 60, 60)]);
        $new = Settings::get('theme.logo');
        $this->assertStringEndsWith('.jpg', $new);
        $this->disk->assertMissing($old);
        $this->disk->assertExists($new);

        $this->save(['site__name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame($new, Settings::get('theme.logo'));
    }

    public function test_remove_clears_the_logo(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('a.png', 50, 50)]);
        $path = Settings::get('theme.logo');

        $this->save(['logo_remove' => '1'])->assertSessionHasNoErrors();

        $this->assertSame('', Settings::get('theme.logo'));
        $this->disk->assertMissing($path);
        $this->get('/')->assertDontSee('wk-logo', false);
    }

    public function test_hiding_the_name_moves_it_into_alt_text(): void
    {
        $this->save(['logo' => UploadedFile::fake()->image('a.png', 50, 50), 'theme__logo_text' => '0']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('class="wk-logo" alt="Team Wiki"', $html);
        $this->assertDoesNotMatchRegularExpression('#wk-logo[^>]*>\s*Team Wiki#', $html);
    }

    public function test_without_a_logo_the_name_shows_even_when_hidden(): void
    {
        $this->save(['theme__logo_text' => '0']);

        $this->get('/')->assertSee('Team Wiki')->assertDontSee('wk-logo', false);
    }

    public function test_rejects_svg_non_images_and_large_files(): void
    {
        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->save(['logo' => $svg])->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');
        $this->save(['logo' => UploadedFile::fake()->image('big.png')->size(1500)])->assertSessionHasErrors('logo');

        $this->assertSame('', Settings::get('theme.logo'));
        $this->assertSame([], $this->disk->allFiles());
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Facades\Settings;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->admin = User::factory()->admin()->create();
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'site__name' => 'Team Wiki',
            'site__email' => 'wiki@example.org',
            'wiki__home_page' => '',
            'wiki__registration' => '0',
            'wiki__secrets' => '1',
            'wiki__backlinks' => '1',
            'meta__description' => '',
            'meta__keywords' => '',
            'custom__css' => '',
            'custom__js' => '',
            'maintenance__enabled' => '0',
            'maintenance__message' => '',
        ];
    }

    public function test_defaults_come_from_config(): void
    {
        config(['app.name' => 'Configured', 'wicket.secrets' => true, 'wicket.registration' => false]);

        $this->assertSame('Configured', Settings::get('site.name'));
        $this->assertTrue(Settings::get('wiki.secrets'));
        $this->assertFalse(Settings::get('wiki.registration'));
        $this->assertNull(Settings::get('wiki.home_page'));
    }

    public function test_values_round_trip_typed_and_cache_is_cleared(): void
    {
        $this->assertSame(config('app.name'), Settings::get('site.name'));

        Settings::set(['site.name' => 'Team Wiki', 'wiki.secrets' => '0', 'wiki.home_page' => '7', 'bogus' => 'x']);

        $this->assertSame('Team Wiki', Settings::get('site.name'));
        $this->assertFalse(Settings::get('wiki.secrets'));
        $this->assertSame(7, Settings::get('wiki.home_page'));
        $this->assertArrayNotHasKey('bogus', Settings::all());
    }

    public function test_settings_override_config_per_request(): void
    {
        Settings::set(['site.name' => 'Team Wiki', 'wiki.registration' => true, 'wiki.secrets' => false]);

        $this->get('/login')->assertSee('Team Wiki');
        $this->get('/register')->assertOk();
        $this->actingAs(User::factory()->editor()->create())->get('/secrets')->assertNotFound();
    }

    public function test_home_page(): void
    {
        $this->get('/')->assertOk()->assertSee('waiting for its first page');

        $home = Page::factory()->create(['title' => 'Welcome Home', 'visibility' => Visibility::Public]);
        $home->contents()->create(['html' => '<p>Start here</p>']);
        Settings::set(['wiki.home_page' => $home->id]);
        $this->get('/')->assertOk()->assertSee(['Welcome Home', 'Start here']);

        $home->update(['visibility' => Visibility::Members]);
        $this->get('/')->assertOk()->assertSee('waiting for its first page');
        $this->actingAs(User::factory()->create())->get('/')->assertSee('Start here');

        $home->delete();
        $this->get('/')->assertOk()->assertSee('waiting for its first page');
    }

    public function test_maintenance_mode(): void
    {
        Settings::set(['maintenance.enabled' => true, 'maintenance.message' => 'Upgrading the server tonight.']);

        $this->get('/')->assertStatus(503)->assertSee('Upgrading the server tonight.');
        $this->actingAs(User::factory()->editor()->create())->get('/')->assertStatus(503);
        auth()->logout();
        $this->get('/login')->assertOk();
        $this->get('/up')->assertOk();
        $this->actingAs($this->admin)->get('/')->assertOk();
    }

    public function test_meta_defaults_apply_only_without_page_meta(): void
    {
        Settings::set(['meta.description' => 'Site wide words']);
        $plain = Page::factory()->create(['slug' => 'plain', 'visibility' => Visibility::Public]);
        $plain->contents()->create(['html' => '<p>x</p>']);
        $own = Page::factory()->create(['slug' => 'own', 'visibility' => Visibility::Public]);
        $own->contents()->create(['html' => '<p>x</p>', 'description' => 'Page words']);

        $this->get('/plain')->assertSee('<meta name="description" content="Site wide words">', false);
        $this->get('/own')->assertSee('<meta name="description" content="Page words">', false)->assertDontSee('Site wide words');
    }

    public function test_custom_code_is_injected_without_breaking_out(): void
    {
        Settings::set(['custom.css' => 'body{color:red}</style><script>alert(1)</script>', 'custom.js' => 'console.log("hi")</script><b>x']);

        $this->get('/login')
            ->assertSee('<style>body{color:red}<\/style><script>alert(1)<\/script></style>', false)
            ->assertSee('<script>console.log("hi")<\/script><b>x</script>', false);
    }

    public function test_admin_page_access_save_and_validation(): void
    {
        $this->actingAs(User::factory()->editor()->create())->get('/admin/settings')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk()->assertSee('Custom code');

        $this->actingAs($this->admin)->put('/admin/settings', $this->form())->assertSessionHasNoErrors()->assertRedirect(route('admin.settings.edit'));
        $this->assertSame('Team Wiki', Settings::get('site.name'));
        $this->assertTrue(Settings::get('wiki.secrets'));

        $this->actingAs($this->admin)->put('/admin/settings', $this->form(['site__name' => '', 'site__email' => 'nope', 'wiki__home_page' => '999']))
            ->assertSessionHasErrors(['site__name', 'site__email', 'wiki__home_page']);
        $this->assertSame('Team Wiki', Settings::get('site.name'));
    }
}

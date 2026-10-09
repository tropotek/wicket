<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecretManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->editor()->create();
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Bank login',
            'visibility' => 'editors',
            'publish' => '1',
            'url' => 'https://bank.example.com',
            'username' => 'treasurer',
            'password' => 'hunter2-but-longer',
            'otp' => '',
            'keys' => '',
            'notes' => 'Call first.',
        ];
    }

    public function test_module_switch_and_access(): void
    {
        $this->get('/secrets')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/secrets')->assertForbidden();
        $this->actingAs(User::factory()->create())->post('/secrets', $this->form())->assertForbidden();
        $this->actingAs($this->editor)->get('/secrets')->assertOk();

        config(['wicket.secrets' => false]);
        $this->actingAs($this->editor)->get('/secrets')->assertNotFound();
        $this->actingAs($this->editor)->get('/')->assertDontSee(route('secrets.index'));
    }

    public function test_list_shows_visible_secrets_without_passwords(): void
    {
        $other = User::factory()->editor()->create();
        Secret::factory()->for($other, 'author')->create(['name' => 'Team WiFi', 'visibility' => Visibility::Members, 'password' => 'wifi-pass-123']);
        Secret::factory()->for($other, 'author')->create(['name' => 'Their Private', 'visibility' => Visibility::Private]);
        Secret::factory()->for($this->editor, 'author')->withOtp()->create(['name' => 'My 2FA', 'visibility' => Visibility::Private]);

        $this->actingAs($this->editor)->get('/secrets')
            ->assertOk()
            ->assertSeeInOrder(['My 2FA', '2FA', 'Team WiFi'])
            ->assertDontSee('Their Private')
            ->assertDontSee('wifi-pass-123');
    }

    public function test_filters_and_htmx_partial(): void
    {
        Secret::factory()->for($this->editor, 'author')->withOtp()->create(['name' => 'Alpha', 'visibility' => Visibility::Members]);
        Secret::factory()->for($this->editor, 'author')->create(['name' => 'Beta', 'visibility' => Visibility::Editors]);

        $htmx = fn (string $q) => $this->actingAs($this->editor)->withHeaders(['HX-Request' => 'true', 'HX-Target' => 'secret-table'])->get('/secrets?'.$q);

        $htmx('q=alp')->assertSee('Alpha')->assertDontSee('Beta')->assertDontSee('<html', false);
        $htmx('visibility=editors')->assertSee('Beta')->assertDontSee('Alpha');
        $htmx('otp=yes')->assertSee('Alpha')->assertDontSee('Beta');
        $htmx('otp=no')->assertSee('Beta')->assertDontSee('Alpha');
    }

    public function test_create_sets_author_and_encrypts(): void
    {
        $this->actingAs($this->editor)->post('/secrets', $this->form())->assertRedirect(route('secrets.index'));

        $secret = Secret::sole();
        $this->assertSame($this->editor->id, $secret->author_id);
        $this->assertSame(Visibility::Editors, $secret->visibility);
        $this->assertSame('hunter2-but-longer', $secret->password);
        $this->assertNull($secret->otp);
        $this->assertNull($secret->keys);
        $this->assertStringNotContainsString('hunter2', DB::table('secrets')->value('password'));
    }

    public function test_otp_accepts_seed_or_otpauth_uri(): void
    {
        $this->actingAs($this->editor)->post('/secrets', $this->form(['otp' => 'jbsw y3dp ehpk 3pxp']));
        $this->assertSame('JBSWY3DPEHPK3PXP', Secret::latest('id')->first()->otp);

        $this->actingAs($this->editor)->post('/secrets', $this->form(['otp' => 'otpauth://totp/Bank:me?secret=GEZDGNBVGY3TQOJQ&issuer=Bank']));
        $this->assertSame('GEZDGNBVGY3TQOJQ', Secret::latest('id')->first()->otp);

        $this->actingAs($this->editor)->post('/secrets', $this->form(['otp' => '123456']))->assertSessionHasErrors('otp');
        $this->actingAs($this->editor)->post('/secrets', $this->form(['otp' => 'otpauth://totp/x?issuer=nope']))->assertSessionHasErrors('otp');
    }

    public function test_validation(): void
    {
        $this->actingAs($this->editor)->post('/secrets', $this->form(['name' => '']))->assertSessionHasErrors('name');
        $this->actingAs($this->editor)->post('/secrets', $this->form(['visibility' => 'public']))->assertSessionHasErrors('visibility');
        $this->actingAs($this->editor)->post('/secrets', $this->form(['url' => 'javascript:alert(1)']))->assertSessionHasErrors('url');

        $this->assertSame(0, Secret::count());
    }

    public function test_edit_follows_the_policy_and_keeps_the_author(): void
    {
        $other = User::factory()->editor()->create();
        $shared = Secret::factory()->for($other, 'author')->create(['visibility' => Visibility::Members]);
        $private = Secret::factory()->for($other, 'author')->create(['visibility' => Visibility::Private]);

        $this->actingAs($this->editor)->get(route('secrets.edit', $shared))->assertOk()->assertSee($shared->name);
        $this->actingAs($this->editor)->put(route('secrets.update', $shared), $this->form(['name' => 'Renamed']))->assertRedirect();
        $this->assertSame('Renamed', $shared->fresh()->name);
        $this->assertSame($other->id, $shared->fresh()->author_id);

        $this->actingAs($this->editor)->get(route('secrets.edit', $private))->assertForbidden();
        $this->actingAs($this->editor)->put(route('secrets.update', $private), $this->form())->assertForbidden();
    }

    public function test_delete_follows_the_policy(): void
    {
        $other = User::factory()->editor()->create();
        $shared = Secret::factory()->for($other, 'author')->create(['visibility' => Visibility::Editors]);
        $private = Secret::factory()->for($other, 'author')->create(['visibility' => Visibility::Private]);

        $this->actingAs($this->editor)->delete(route('secrets.destroy', $private))->assertForbidden();
        $this->actingAs($this->editor)->from('/secrets')->delete(route('secrets.destroy', $shared))->assertRedirect('/secrets');

        $this->assertSame([$private->id], Secret::pluck('id')->all());
    }
}

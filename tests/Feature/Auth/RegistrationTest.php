<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'username' => 'testuser',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ], $overrides);
    }

    public function test_registration_is_disabled_by_default(): void
    {
        $this->assertFalse(config('wicket.registration'));

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->validPayload())->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_page_hides_register_link_when_disabled(): void
    {
        $this->get('/login')->assertDontSee('href="'.route('register').'"', false);
    }

    public function test_registration_screen_can_be_rendered_when_enabled(): void
    {
        config(['wicket.registration' => true]);

        $this->get('/register')->assertOk()->assertSee('name="username"', false);
        $this->get('/login')->assertSee('href="'.route('register').'"', false);
    }

    public function test_new_users_can_register_as_active_members(): void
    {
        config(['wicket.registration' => true]);

        $this->post('/register', $this->validPayload())
            ->assertRedirect(route('home', absolute: false));

        $user = User::where('username', 'testuser')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(Role::Member, $user->role);
        $this->assertTrue($user->active);
    }

    public function test_registration_ignores_role_in_payload(): void
    {
        config(['wicket.registration' => true]);

        $this->post('/register', $this->validPayload(['role' => 'admin', 'active' => '0']));

        $user = User::where('username', 'testuser')->firstOrFail();
        $this->assertSame(Role::Member, $user->role);
        $this->assertTrue($user->active);
    }

    public function test_username_must_be_unique_and_slug_like(): void
    {
        config(['wicket.registration' => true]);
        User::factory()->create(['username' => 'taken']);

        $this->post('/register', $this->validPayload(['username' => 'taken']))
            ->assertSessionHasErrors('username');
        $this->post('/register', $this->validPayload(['username' => 'has space@x']))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}

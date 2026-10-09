<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="login"', false);
    }

    public function test_users_can_authenticate_with_email(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('home', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_authenticate_with_username(): void
    {
        $user = User::factory()->create(['username' => 'bob']);

        $this->post('/login', ['login' => 'bob', 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['login' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_inactive_users_get_the_generic_error(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['login' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors(['login' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_login_records_last_login_at(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_remember_me_sets_recaller_cookie(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['login' => $user->email, 'password' => 'password', 'remember' => 'on'])
            ->assertCookie(auth()->guard()->getRecallerName());
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['login' => $user->email, 'password' => 'nope']);
        }

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_navbar_shows_logout_for_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $this->actingAs($user)->get('/')
            ->assertSee('Jane Doe')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertDontSee('href="'.route('login').'"', false);
    }

    public function test_navbar_shows_login_link_for_guest(): void
    {
        $this->get('/')->assertSee('href="'.route('login').'"', false);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_authenticated_users_are_redirected_away_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect();
    }
}

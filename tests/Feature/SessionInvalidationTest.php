<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(User $user): void
    {
        $this->post('/login', ['login' => $user->username, 'password' => 'password']);
        $this->get('/'); // AuthenticateSession stores the password hash on the first authed request
        $this->assertAuthenticatedAs($user);
    }

    /** The test client keeps the resolved user between requests; a real request starts fresh. */
    private function nextRequest(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->create();
        $this->loginAs($user);

        $user->forceFill(['active' => false])->save();
        $this->nextRequest();

        $this->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_password_changed_elsewhere_ends_existing_session(): void
    {
        $user = User::factory()->create();
        $this->loginAs($user);

        $user->forceFill(['password' => 'changed-elsewhere'])->save();
        $this->nextRequest();

        $this->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_own_password_change_keeps_current_session(): void
    {
        $user = User::factory()->create();
        $this->loginAs($user);

        $this->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();
        $this->nextRequest();

        $this->get('/profile')->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}

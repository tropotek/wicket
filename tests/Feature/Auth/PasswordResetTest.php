<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get('/login')->assertSee('href="'.route('password.request').'"', false);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', 'If that email belongs to an account, a reset link is on its way.');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_response(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'If that email belongs to an account, a reset link is on its way.');

        Notification::assertNothingSent();
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
                ->assertOk()
                ->assertSee('value="'.$user->email.'"', false);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });

        $this->post('/login', ['login' => $user->email, 'password' => 'new-password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->post('/reset-password', [
            'token' => 'bogus',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');
    }
}

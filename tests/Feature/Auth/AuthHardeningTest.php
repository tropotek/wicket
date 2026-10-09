<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function failLogin(string $login, int $times = 1): void
    {
        foreach (range(1, $times) as $i) {
            $this->post('/login', ['login' => $login, 'password' => 'wrong-password']);
        }
    }

    public function test_lockout_lasts_fifteen_minutes(): void
    {
        $user = User::factory()->create();
        $this->failLogin($user->email, 5);

        $this->travel(14)->minutes();
        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->travel(2)->minutes();
        $this->post('/login', ['login' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user);
    }

    public function test_alternating_email_and_username_shares_one_budget(): void
    {
        $user = User::factory()->create(['username' => 'bob', 'email' => 'bob@example.com']);

        $this->failLogin('bob', 3);
        $this->failLogin('BOB@example.com', 2);

        $this->post('/login', ['login' => 'bob', 'password' => 'password'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_unknown_user_gets_the_generic_error(): void
    {
        $this->post('/login', ['login' => 'nobody', 'password' => 'whatever'])
            ->assertSessionHasErrors(['login' => trans('auth.failed')]);
    }

    public function test_unknown_users_are_throttled_too(): void
    {
        $this->failLogin('nobody@example.com', 5);

        $this->post('/login', ['login' => 'nobody@example.com', 'password' => 'whatever'])
            ->assertSessionHasErrors('login')
            ->assertSessionDoesntHaveErrors(['login' => trans('auth.failed')]);
    }

    public function test_session_id_is_regenerated_on_login(): void
    {
        $user = User::factory()->create();

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['login' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_failed_login_for_unknown_user_is_timeboxed(): void
    {
        $start = microtime(true);
        $this->post('/login', ['login' => 'nobody', 'password' => 'whatever']);

        $this->assertGreaterThanOrEqual(0.2, microtime(true) - $start);
    }

    public function test_reset_mail_failure_is_reported_but_response_is_unchanged(): void
    {
        Exceptions::fake();
        $this->mock(Dispatcher::class)->shouldReceive('send')->andThrow(new RuntimeException('smtp down'));

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'If that email belongs to an account, a reset link is on its way.');

        Exceptions::assertReported(RuntimeException::class);
    }
}

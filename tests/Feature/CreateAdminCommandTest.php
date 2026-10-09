<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotes_existing_user_by_username(): void
    {
        $user = User::factory()->create(['username' => 'alice']);

        $this->artisan('wicket:create-admin', ['login' => 'alice'])->assertSuccessful();

        $this->assertSame(Role::Admin, $user->fresh()->role);
    }

    public function test_promotes_existing_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'alice@example.com']);

        $this->artisan('wicket:create-admin', ['login' => 'Alice@Example.com'])->assertSuccessful();

        $this->assertSame(Role::Admin, $user->fresh()->role);
    }

    public function test_promotion_reactivates_inactive_user(): void
    {
        $user = User::factory()->inactive()->create(['username' => 'alice']);

        $this->artisan('wicket:create-admin', ['login' => 'alice'])->assertSuccessful();

        $this->assertTrue($user->fresh()->active);
    }

    public function test_existing_admin_is_left_alone(): void
    {
        User::factory()->admin()->create(['username' => 'root']);

        $this->artisan('wicket:create-admin', ['login' => 'root'])
            ->expectsOutputToContain('already an admin')
            ->assertSuccessful();
    }

    public function test_unknown_username_creates_new_admin(): void
    {
        $this->artisan('wicket:create-admin', ['login' => 'newboss'])
            ->expectsQuestion('Name', 'New Boss')
            ->expectsQuestion('Email', 'newboss@example.com')
            ->expectsQuestion('Password', 'secret-pass')
            ->assertSuccessful();

        $user = User::where('username', 'newboss')->firstOrFail();
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->active);
    }

    public function test_unknown_email_fails(): void
    {
        $this->artisan('wicket:create-admin', ['login' => 'ghost@example.com'])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}

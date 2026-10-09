<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_active_user_from_options(): void
    {
        $this->artisan('wicket:create-user', [
            'username' => 'boss',
            '--name' => 'The Boss',
            '--email' => 'boss@example.com',
            '--role' => 'admin',
            '--password' => 'secret-pass',
        ])->assertSuccessful();

        $user = User::where('username', 'boss')->firstOrFail();
        $this->assertSame(Role::Admin, $user->role);
        $this->assertTrue($user->active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_prompts_for_missing_values(): void
    {
        $this->artisan('wicket:create-user', ['username' => 'eve'])
            ->expectsQuestion('Name', 'Eve')
            ->expectsQuestion('Email', 'eve@example.com')
            ->expectsQuestion('Password', 'secret-pass')
            ->assertSuccessful();

        $this->assertSame(Role::Member, User::where('username', 'eve')->firstOrFail()->role);
    }

    public function test_rejects_invalid_role(): void
    {
        $this->artisan('wicket:create-user', [
            'username' => 'x',
            '--name' => 'X',
            '--email' => 'x@example.com',
            '--role' => 'superuser',
            '--password' => 'secret-pass',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_duplicate_username(): void
    {
        User::factory()->create(['username' => 'dup']);

        $this->artisan('wicket:create-user', [
            'username' => 'dup',
            '--name' => 'Dup',
            '--email' => 'dup2@example.com',
            '--password' => 'secret-pass',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}

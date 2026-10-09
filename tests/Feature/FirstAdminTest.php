<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FirstAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_email_it_only_explains(): void
    {
        config(['wicket.first_admin' => ['username' => 'admin', 'email' => null, 'password' => null]]);

        $this->artisan('wicket:first-admin')->expectsOutputToContain('No admin yet')->assertSuccessful();
        $this->assertSame(0, User::count());
    }

    public function test_creates_the_first_admin_with_the_given_password(): void
    {
        config(['wicket.first_admin' => ['username' => 'boss', 'email' => 'boss@example.org', 'password' => 'Correct-Horse-9']]);

        $this->artisan('wicket:first-admin')->doesntExpectOutputToContain('shown once')->assertSuccessful();

        $admin = User::sole();
        $this->assertSame(['boss', Role::Admin], [$admin->username, $admin->role]);
        $this->assertTrue(Hash::check('Correct-Horse-9', $admin->password));
    }

    public function test_generates_and_prints_a_password_once(): void
    {
        config(['wicket.first_admin' => ['username' => 'admin', 'email' => 'admin@example.org', 'password' => null]]);

        $this->artisan('wicket:first-admin')->expectsOutputToContain('password (shown once)')->assertSuccessful();
        $this->assertSame(1, User::where('role', Role::Admin)->count());
    }

    public function test_does_nothing_once_an_admin_exists(): void
    {
        User::factory()->admin()->create();
        config(['wicket.first_admin' => ['username' => 'second', 'email' => 'second@example.org', 'password' => null]]);

        $this->artisan('wicket:first-admin')->doesntExpectOutputToContain('shown once')->assertSuccessful();
        $this->assertSame(1, User::count());
    }
}

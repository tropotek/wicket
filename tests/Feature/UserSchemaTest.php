<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_auth_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'username', 'name', 'email', 'password', 'role',
            'active', 'timezone', 'last_login_at', 'remember_token',
        ]));
    }

    public function test_factory_defaults_to_active_member(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Role::Member, $user->role);
        $this->assertTrue($user->active);
        $this->assertNotEmpty($user->username);
        $this->assertNull($user->last_login_at);
    }

    public function test_factory_states(): void
    {
        $this->assertSame(Role::Admin, User::factory()->admin()->create()->role);
        $this->assertSame(Role::Editor, User::factory()->editor()->create()->role);
        $this->assertSame(Role::Guest, User::factory()->guest()->create()->role);
        $this->assertFalse(User::factory()->inactive()->create()->active);
    }

    public function test_mass_assignment_ignores_role_and_active(): void
    {
        $user = User::create([
            'username' => 'mallory',
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-pass',
            'role' => 'admin',
            'active' => false,
        ])->fresh();

        $this->assertSame(Role::Member, $user->role);
        $this->assertTrue($user->active);
    }

    public function test_username_and_email_are_unique(): void
    {
        User::factory()->create(['username' => 'dup']);

        $this->expectException(QueryException::class);
        User::factory()->create(['username' => 'dup']);
    }

    public function test_seeder_creates_admin(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(Role::Admin, User::where('username', 'admin')->firstOrFail()->role);
    }
}

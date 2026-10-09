<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['username' => 'root']);
    }

    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'active' => $user->active ? '1' : '0',
        ], $overrides);
    }

    public function test_non_admins_are_forbidden(): void
    {
        $editor = User::factory()->editor()->create();
        $target = User::factory()->create();

        $this->actingAs($editor);
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/users/create')->assertForbidden();
        $this->post('/admin/users', [])->assertForbidden();
        $this->get("/admin/users/{$target->id}/edit")->assertForbidden();
        $this->put("/admin/users/{$target->id}", [])->assertForbidden();
    }

    public function test_index_lists_and_searches_users(): void
    {
        User::factory()->create(['username' => 'alice', 'name' => 'Alice A']);
        User::factory()->create(['username' => 'bob', 'name' => 'Bob B']);

        $this->actingAs($this->admin)->get('/admin/users')
            ->assertOk()->assertSee('alice')->assertSee('bob');

        $this->actingAs($this->admin)->get('/admin/users?q=ali')
            ->assertOk()->assertSee('alice')->assertDontSee('Bob B');
    }

    public function test_htmx_request_returns_only_the_table(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/users?q=root', ['HX-Request' => 'true', 'HX-Target' => 'user-table'])
            ->assertOk()
            ->assertSee('root')
            ->assertDontSee('<html', false)
            ->assertSee('id="user-table"', false);
    }

    public function test_index_paginates(): void
    {
        User::factory()->count(30)->sequence(fn ($s) => ['username' => sprintf('user%02d', $s->index)])->create();

        $this->actingAs($this->admin)->get('/admin/users')
            ->assertSee('user00')->assertDontSee('user29');
        $this->actingAs($this->admin)->get('/admin/users?page=2')
            ->assertSee('user29')->assertDontSee('user00');
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'username' => 'newbie',
            'name' => 'New Bie',
            'email' => 'newbie@example.com',
            'role' => 'editor',
            'active' => '1',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $user = User::where('username', 'newbie')->firstOrFail();
        $this->assertSame(Role::Editor, $user->role);
        $this->assertTrue(Hash::check('secret-pass', $user->password));
    }

    public function test_create_requires_password(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'username' => 'newbie', 'name' => 'N', 'email' => 'n@example.com', 'role' => 'member', 'active' => '1',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", $this->payload($user, [
            'username' => 'renamed',
            'name' => 'Renamed',
            'email' => 'renamed@example.com',
            'role' => 'editor',
            'active' => '0',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('renamed', $user->username);
        $this->assertSame('renamed@example.com', $user->email);
        $this->assertSame(Role::Editor, $user->role);
        $this->assertFalse($user->active);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_admin_can_set_a_new_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->admin)->put("/admin/users/{$user->id}", $this->payload($user, [
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
    }

    public function test_email_and_username_must_be_unique(): void
    {
        User::factory()->create(['username' => 'taken', 'email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($this->admin)
            ->put("/admin/users/{$user->id}", $this->payload($user, ['username' => 'taken', 'email' => 'taken@example.com']))
            ->assertSessionHasErrors(['username', 'email']);
    }

    public function test_admin_cannot_change_own_role_or_active_flag(): void
    {
        User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->put("/admin/users/{$this->admin->id}", $this->payload($this->admin, ['role' => 'member', 'active' => '0']))
            ->assertSessionHasErrors(['role', 'active']);

        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_admin_can_edit_own_details(): void
    {
        $this->actingAs($this->admin)
            ->put("/admin/users/{$this->admin->id}", $this->payload($this->admin, ['name' => 'Root Admin']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Root Admin', $this->admin->fresh()->name);
    }

    public function test_another_admin_can_be_demoted_while_one_remains(): void
    {
        $other = User::factory()->admin()->create();

        $this->actingAs($this->admin)
            ->put("/admin/users/{$other->id}", $this->payload($other, ['role' => 'editor', 'active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertSame(Role::Editor, $other->fresh()->role);
    }

    public function test_last_active_admin_detection(): void
    {
        $this->assertTrue($this->admin->isLastActiveAdmin());

        $inactive = User::factory()->admin()->inactive()->create();
        $this->assertTrue($this->admin->fresh()->isLastActiveAdmin());
        $this->assertFalse($inactive->isLastActiveAdmin());

        User::factory()->admin()->create();
        $this->assertFalse($this->admin->fresh()->isLastActiveAdmin());
    }
}

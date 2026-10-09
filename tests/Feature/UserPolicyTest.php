<?php

namespace Tests\Feature;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_is_auto_registered(): void
    {
        $this->assertInstanceOf(UserPolicy::class, Gate::getPolicyFor(User::class));
    }

    public function test_admin_abilities(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('view', $other));
        $this->assertTrue($admin->can('update', $other));
        $this->assertTrue($admin->can('changeAccess', $other));
        $this->assertTrue($admin->can('delete', $other));
    }

    public function test_admin_cannot_change_own_access_or_delete_self(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->assertTrue($admin->can('update', $admin));
        $this->assertFalse($admin->can('changeAccess', $admin));
        $this->assertFalse($admin->can('delete', $admin));
    }

    public function test_last_active_admin_cannot_be_deleted(): void
    {
        $actor = User::factory()->admin()->create();
        $target = User::factory()->admin()->create();
        $this->assertTrue($actor->can('delete', $target));

        // $actor deactivated mid-session: $target is now the last active admin.
        $actor->forceFill(['active' => false])->save();
        $this->assertFalse($actor->can('delete', $target->fresh()));
    }

    public function test_non_admins_only_reach_themselves(): void
    {
        foreach ([User::factory()->editor()->create(), User::factory()->create()] as $user) {
            $other = User::factory()->create();

            $this->assertFalse($user->can('viewAny', User::class));
            $this->assertFalse($user->can('create', User::class));
            $this->assertTrue($user->can('view', $user));
            $this->assertTrue($user->can('update', $user));
            $this->assertFalse($user->can('view', $other));
            $this->assertFalse($user->can('update', $other));
            $this->assertFalse($user->can('changeAccess', $user));
            $this->assertFalse($user->can('delete', $other));
        }
    }

    public function test_form_disables_access_fields_only_for_self(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.users.edit', $admin))
            ->assertSee('You cannot change your own role.');
        $this->actingAs($admin)->get(route('admin.users.edit', $other))
            ->assertDontSee('You cannot change your own role.');
    }
}

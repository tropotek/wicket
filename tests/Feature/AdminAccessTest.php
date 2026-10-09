<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_out_visitor_is_sent_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_non_admin_roles_are_forbidden(): void
    {
        $users = [
            User::factory()->guest()->create(),
            User::factory()->create(),
            User::factory()->editor()->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get('/admin')->assertForbidden();
        }
    }

    public function test_admin_can_open_admin_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk();
    }

    public function test_navbar_admin_link_only_for_admins(): void
    {
        $link = 'href="'.route('admin.index').'"';

        $this->actingAs(User::factory()->editor()->create())->get('/')->assertDontSee($link, false);
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertSee($link, false);
    }

    public function test_role_middleware_allows_higher_roles(): void
    {
        Route::middleware(['web', 'auth', 'role:editor'])->get('/_test/editor', fn () => 'ok');

        $this->actingAs(User::factory()->create())->get('/_test/editor')->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())->get('/_test/editor')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/_test/editor')->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create(['username' => 'alice']);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('alice')
            ->assertSee('name="timezone"', false);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_name_and_timezone_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'New Name',
            'email' => $user->email,
            'timezone' => 'Australia/Melbourne',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('Australia/Melbourne', $user->timezone);
    }

    public function test_blank_timezone_means_default(): void
    {
        $user = User::factory()->create(['timezone' => 'Europe/London']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name, 'email' => $user->email, 'timezone' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->timezone);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name, 'email' => $user->email, 'timezone' => 'Mars/Olympus',
        ])->assertSessionHasErrors('timezone');
    }

    public function test_email_change_requires_current_password(): void
    {
        $user = User::factory()->create();
        $payload = ['name' => $user->name, 'email' => 'new@example.com'];

        $this->actingAs($user)->patch('/profile', $payload)
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->patch('/profile', $payload + ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');
        $this->assertNotSame('new@example.com', $user->fresh()->email);

        $this->actingAs($user)->patch('/profile', $payload + ['current_password' => 'password'])
            ->assertSessionHasNoErrors();
        $this->assertSame('new@example.com', $user->fresh()->email);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name, 'email' => 'taken@example.com', 'current_password' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_protected_fields_are_ignored(): void
    {
        $user = User::factory()->create(['username' => 'alice']);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name, 'email' => $user->email,
            'username' => 'root', 'role' => 'admin', 'active' => '0',
        ]);

        $user->refresh();
        $this->assertSame('alice', $user->username);
        $this->assertSame(Role::Member, $user->role);
        $this->assertTrue($user->active);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'wrong',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'other-password',
        ])->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_password_can_be_changed(): void
    {
        $user = User::factory()->create();
        $oldToken = $user->remember_token;

        $this->actingAs($user)->put('/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors()->assertRedirect('/profile');

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame($oldToken, $user->remember_token);
    }

    public function test_navbar_links_to_profile(): void
    {
        $this->actingAs(User::factory()->create())->get('/')
            ->assertSee('href="'.route('profile.edit').'"', false);
    }
}

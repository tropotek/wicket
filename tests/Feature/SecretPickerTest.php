<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Page;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecretPickerTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->editor()->create();
    }

    public function test_access_and_module_switch(): void
    {
        $this->actingAs(User::factory()->create())->get('/secrets/picker')->assertForbidden();
        $this->actingAs($this->editor)->get('/secrets/picker')->assertOk();

        config(['wicket.secrets' => false]);
        $this->actingAs($this->editor)->get('/secrets/picker')->assertNotFound();
    }

    public function test_lists_visible_secrets_with_insert_buttons(): void
    {
        $other = User::factory()->editor()->create();
        Secret::factory()->for($other, 'author')->create(['name' => 'Shared WiFi', 'visibility' => Visibility::Editors]);
        Secret::factory()->for($other, 'author')->create(['name' => 'Their Private']);

        $this->actingAs($this->editor)->get('/secrets/picker')
            ->assertOk()
            ->assertSee('Shared WiFi')
            ->assertDontSee('Their Private')
            ->assertDontSee('navbar', false)
            ->assertSee('wicketSecretPick', false)
            ->assertDontSee('Inserting');
    }

    public function test_search_paging_and_htmx_partial(): void
    {
        foreach (range(1, 12) as $i) {
            Secret::factory()->for($this->editor, 'author')->create(['name' => sprintf('Key %02d', $i)]);
        }

        $this->actingAs($this->editor)->get('/secrets/picker')->assertSee('Key 10')->assertDontSee('Key 11');
        $this->actingAs($this->editor)->withHeaders(['HX-Target' => 'secret-picker'])->get('/secrets/picker?q=key+12')
            ->assertSee('Key 12')->assertDontSee('Key 01')->assertDontSee('<html', false);
    }

    public function test_create_from_the_picker_hands_the_new_secret_back(): void
    {
        $this->actingAs($this->editor)->get('/secrets/create?picker=1')
            ->assertOk()
            ->assertDontSee('navbar', false)
            ->assertSee('name="picker" value="1"', false)
            ->assertSee(route('secrets.picker'));

        $response = $this->actingAs($this->editor)->post('/secrets', [
            'name' => 'Router admin', 'visibility' => 'editors', 'publish' => '1', 'picker' => '1',
        ]);
        $secret = Secret::sole();
        $response->assertRedirect(route('secrets.picker', ['pick' => $secret->id]));

        $this->actingAs($this->editor)->get(route('secrets.picker', ['pick' => $secret->id]))
            ->assertSee('Inserting')
            ->assertSee("id: {$secret->id}, name: 'Router admin'", false);
    }

    public function test_pick_ignores_secrets_the_user_cannot_see(): void
    {
        $hidden = Secret::factory()->for(User::factory()->editor()->create(), 'author')->create(['name' => 'Not yours']);

        $this->actingAs($this->editor)->get(route('secrets.picker', ['pick' => $hidden->id]))
            ->assertOk()
            ->assertDontSee('Inserting')
            ->assertDontSee('Not yours');
    }

    public function test_page_form_offers_the_button_only_when_allowed(): void
    {
        $page = Page::factory()->for($this->editor, 'author')->withContent()->create();

        $this->actingAs($this->editor)->get(route('pages.edit', $page))
            ->assertSee('data-secret-picker-url="'.route('secrets.picker').'"', false);

        config(['wicket.secrets' => false]);
        $this->actingAs($this->editor)->get(route('pages.edit', $page))->assertDontSee('data-secret-picker-url', false);
    }
}

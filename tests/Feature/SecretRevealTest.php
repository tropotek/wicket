<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SecretRevealTest extends TestCase
{
    use RefreshDatabase;

    /** RFC 6238 SHA1 test seed ("12345678901234567890" in base32). */
    private const RFC_SEED = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->editor = User::factory()->editor()->create();
    }

    private function secret(array $attrs = [], ?User $author = null): Secret
    {
        return Secret::factory()->for($author ?? $this->editor, 'author')->create($attrs + [
            'name' => 'Bank',
            'username' => 'treasurer',
            'password' => 'pa55-word-xyz',
            'keys' => 'KEY-MATERIAL-123',
            'notes' => 'NOTE-TEXT-456',
            'otp' => self::RFC_SEED,
        ]);
    }

    public function test_reveal_returns_values_and_the_current_code(): void
    {
        $this->travelTo(Carbon::createFromTimestampUTC(59));
        $secret = $this->secret();

        $this->actingAs($this->editor)->postJson(route('secrets.reveal', $secret))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'password' => 'pa55-word-xyz',
                'keys' => 'KEY-MATERIAL-123',
                'notes' => 'NOTE-TEXT-456',
                'otp' => ['code' => '287082', 'period' => 30, 'remaining' => 1],
            ]);
    }

    public function test_reveal_without_otp_returns_null(): void
    {
        $secret = $this->secret(['otp' => null]);

        $this->actingAs($this->editor)->postJson(route('secrets.reveal', $secret))->assertJsonPath('otp', null);
    }

    public function test_reveal_follows_the_view_policy(): void
    {
        $other = User::factory()->editor()->create();
        $private = $this->secret(['visibility' => Visibility::Private], $other);
        $editorsOnly = $this->secret(['visibility' => Visibility::Editors], $other);
        $unpublished = $this->secret(['visibility' => Visibility::Members, 'publish' => false], $other);

        $this->postJson(route('secrets.reveal', $editorsOnly))->assertUnauthorized();
        $this->actingAs($this->editor)->postJson(route('secrets.reveal', $private))->assertForbidden();
        $this->actingAs($this->editor)->postJson(route('secrets.reveal', $unpublished))->assertForbidden();
        $this->actingAs($this->editor)->postJson(route('secrets.reveal', $editorsOnly))->assertOk();
        $this->actingAs(User::factory()->create())->postJson(route('secrets.reveal', $editorsOnly))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->postJson(route('secrets.reveal', $private))->assertOk();
    }

    public function test_reveal_is_post_only_and_module_gated(): void
    {
        $secret = $this->secret();

        $this->actingAs($this->editor)->get(route('secrets.reveal', $secret))->assertMethodNotAllowed();

        config(['wicket.secrets' => false]);
        $this->actingAs($this->editor)->postJson(route('secrets.reveal', $secret))->assertNotFound();
    }

    public function test_card_markup_never_contains_protected_values(): void
    {
        $secret = $this->secret(['url' => 'https://bank.example.com']);

        $this->actingAs($this->editor)->get(route('secrets.show', $secret))
            ->assertOk()
            ->assertSee('treasurer')
            ->assertSee('https://bank.example.com')
            ->assertSee(str_replace('/', '\\/', route('secrets.reveal', $secret)), false)
            ->assertSee(['Show code', 'Show details'])
            ->assertDontSee('pa55-word-xyz')
            ->assertDontSee('KEY-MATERIAL-123')
            ->assertDontSee('NOTE-TEXT-456')
            ->assertDontSee(self::RFC_SEED);
    }

    public function test_card_hides_rows_for_empty_fields_and_unsafe_urls(): void
    {
        $secret = $this->secret(['password' => null, 'otp' => null, 'keys' => null, 'notes' => null, 'url' => 'javascript:alert(1)']);

        $this->actingAs($this->editor)->get(route('secrets.show', $secret))
            ->assertOk()
            ->assertDontSee('Show code')
            ->assertDontSee('Show details')
            ->assertDontSee('Show password')
            ->assertDontSee('javascript:alert', false);
    }

    public function test_show_follows_the_view_policy(): void
    {
        $private = $this->secret([], User::factory()->editor()->create());

        $this->actingAs($this->editor)->get(route('secrets.show', $private))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('secrets.show', $private))->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecretSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const FIELDS = ['url', 'username', 'password', 'otp', 'keys', 'notes'];

    private function values(): array
    {
        return [
            'url' => 'https://bank.example.com/login',
            'username' => 'treasurer',
            'password' => 'correct horse battery staple',
            'otp' => 'JBSWY3DPEHPK3PXP',
            'keys' => "-----BEGIN KEY-----\nabc\n-----END KEY-----",
            'notes' => 'Ring Sam before using.',
        ];
    }

    public function test_values_round_trip(): void
    {
        $secret = Secret::factory()->create(['name' => 'Bank'] + $this->values());

        $fresh = Secret::find($secret->id);
        foreach ($this->values() as $field => $value) {
            $this->assertSame($value, $fresh->{$field});
        }
        $this->assertSame('Bank', $fresh->name);
        $this->assertSame(Visibility::Private, $fresh->visibility);
        $this->assertTrue($fresh->publish);
    }

    public function test_values_are_encrypted_at_rest(): void
    {
        $secret = Secret::factory()->create(['name' => 'Bank'] + $this->values());
        $raw = (array) DB::table('secrets')->find($secret->id);

        $this->assertSame('Bank', $raw['name']);
        foreach ($this->values() as $field => $value) {
            $this->assertStringNotContainsString($value, $raw[$field]);
            $this->assertSame($value, Crypt::decryptString($raw[$field]));
        }
    }

    public function test_empty_fields_stay_null(): void
    {
        $secret = Secret::factory()->create(['url' => null, 'username' => null, 'password' => null]);
        $raw = (array) DB::table('secrets')->find($secret->id);

        foreach (self::FIELDS as $field) {
            $this->assertNull($raw[$field], $field);
        }
    }

    public function test_decrypted_values_are_hidden_from_serialisation(): void
    {
        $json = Secret::factory()->create($this->values())->toArray();

        foreach (self::FIELDS as $field) {
            $this->assertArrayNotHasKey($field, $json);
        }
    }

    public function test_deleting_the_author_keeps_the_secret(): void
    {
        $author = User::factory()->editor()->create();
        $secret = Secret::factory()->for($author, 'author')->create();

        $author->delete();

        $this->assertNull($secret->fresh()->author_id);
    }

    public function test_author_is_not_mass_assignable(): void
    {
        $secret = new Secret(['name' => 'x', 'author_id' => 5]);

        $this->assertNull($secret->author_id);
    }
}

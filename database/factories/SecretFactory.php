<?php

namespace Database\Factories;

use App\Enums\Visibility;
use App\Models\Secret;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Secret>
 */
class SecretFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory()->editor(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'url' => fake()->url(),
            'username' => fake()->userName(),
            'password' => fake()->password(12),
            'visibility' => Visibility::Private,
            'publish' => true,
        ];
    }

    /** A 2FA seed (base32). */
    public function withOtp(): static
    {
        return $this->state(fn (array $attributes) => ['otp' => 'JBSWY3DPEHPK3PXP']);
    }
}

<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Content>
 */
class ContentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'user_id' => User::factory(),
            'html' => collect(fake()->paragraphs(3))->map(fn ($p) => "<p>{$p}</p>")->implode("\n"),
            'css' => null,
            'js' => null,
            'keywords' => '',
            'description' => '',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Content;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(3), '.');

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'category' => '',
            'template' => '',
            'visibility' => 'public',
            'author_id' => User::factory(),
            'publish' => true,
            'title_visible' => true,
            'views' => 0,
        ];
    }

    /** Give the page $revisions revisions, saved by its author. */
    public function withContent(int $revisions = 1): static
    {
        return $this->afterCreating(function (Page $page) use ($revisions) {
            Content::factory()->count($revisions)->for($page)->create(['user_id' => $page->author_id]);
        });
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => ['publish' => false]);
    }
}

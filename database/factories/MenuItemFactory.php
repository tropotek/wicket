<?php

namespace Database\Factories;

use App\Enums\MenuItemType;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => MenuItemType::Item,
            'name' => ucfirst(fake()->word()),
            'page_id' => Page::factory(),
            'sort' => 0,
        ];
    }

    public function dropdown(): static
    {
        return $this->state(fn (array $attributes) => ['type' => MenuItemType::Dropdown, 'page_id' => null]);
    }

    public function divider(): static
    {
        return $this->state(fn (array $attributes) => ['type' => MenuItemType::Divider, 'page_id' => null, 'name' => '']);
    }
}

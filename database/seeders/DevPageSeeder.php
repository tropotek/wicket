<?php

namespace Database\Seeders;

use App\Enums\Visibility;
use App\Models\Content;
use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One dev page per visibility level, authored by the seeded editor.
 */
class DevPageSeeder extends Seeder
{
    public function run(): void
    {
        $editor = User::where('username', 'editor')->firstOrFail();

        $pages = [
            ['public-page', 'Public page', Visibility::Public],
            ['members-page', 'Members page', Visibility::Members],
            ['editors-page', 'Editors page', Visibility::Editors],
            ['private-page', 'Private page', Visibility::Private],
        ];

        foreach ($pages as [$slug, $title, $visibility]) {
            $page = Page::factory()->for($editor, 'author')->create([
                'slug' => $slug,
                'title' => $title,
                'visibility' => $visibility,
            ]);

            Content::factory()->for($page)->for($editor)->create([
                'html' => "<p>This page is <strong>{$visibility->label()}</strong>.</p>\n<p>".fake()->paragraph().'</p>',
                'description' => "Seeded {$visibility->value} page",
            ]);
        }
    }
}

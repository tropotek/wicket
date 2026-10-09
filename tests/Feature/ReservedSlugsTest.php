<?php

namespace Tests\Feature;

use App\Support\ReservedSlugs;
use Tests\TestCase;

class ReservedSlugsTest extends TestCase
{
    public function test_fixed_route_segments_and_public_entries_are_reserved(): void
    {
        $reserved = app(ReservedSlugs::class)->all();

        foreach (['login', 'logout', 'register', 'forgot-password', 'reset-password', 'profile', 'admin', 'pages', 'up', 'build', 'favicon.ico'] as $slug) {
            $this->assertContains($slug, $reserved, $slug);
        }
    }

    public function test_route_placeholders_are_not_reserved(): void
    {
        foreach (app(ReservedSlugs::class)->all() as $slug) {
            $this->assertStringNotContainsString('{', $slug);
        }
    }
}

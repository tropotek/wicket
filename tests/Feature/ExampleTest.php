<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_footer_version_links_to_the_repository(): void
    {
        $this->get('/')->assertSee('href="'.config('wicket.repo_url').'"', false)
            ->assertSee('Wicket v'.config('app.version'));
    }
}

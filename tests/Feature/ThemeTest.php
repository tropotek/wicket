<?php

namespace Tests\Feature;

use App\Facades\Settings;
use App\Support\Theme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_need_no_css(): void
    {
        $this->assertSame('', app(Theme::class)->css());
        $this->get('/')->assertDontSee('wk-theme', false);
    }

    public function test_accent_colour_overrides_bootstrap_variables(): void
    {
        Settings::set(['theme.accent' => '#6F42C1']);
        $css = app(Theme::class)->css();

        $this->assertStringContainsString('--bs-primary: #6f42c1;', $css);
        $this->assertStringContainsString('--bs-primary-rgb: 111, 66, 193;', $css);
        $this->assertStringContainsString('--bs-link-color: #6f42c1;', $css);
        $this->assertStringContainsString('.btn-primary', $css);
        $this->assertStringContainsString('--bs-btn-color: #fff;', $css);
        $this->assertStringContainsString('html[data-bs-theme=dark] {', $css);
        $this->assertStringNotContainsString('<', $css);
    }

    public function test_light_accents_get_dark_button_text(): void
    {
        Settings::set(['theme.accent' => '#ffc107']);

        $this->assertStringContainsString('--bs-btn-color: #000;', app(Theme::class)->css());
    }

    public function test_radius_and_font(): void
    {
        Settings::set(['theme.radius' => 'sharp', 'theme.font' => 'serif']);
        $css = app(Theme::class)->css();

        $this->assertStringContainsString('--bs-border-radius: 0;', $css);
        $this->assertStringContainsString('--bs-body-font-family: Georgia', $css);
        $this->assertStringNotContainsString('--bs-primary:', $css);
    }

    public function test_block_is_in_every_layout_before_custom_css(): void
    {
        Settings::set(['theme.accent' => '#198754', 'custom.css' => 'body{}']);

        $html = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('<style id="wk-theme">', $html);
        $this->assertLessThan(strpos($html, 'body{}'), strpos($html, 'wk-theme'));
    }

    public function test_default_mode_feeds_the_head_script(): void
    {
        $this->get('/login')->assertSee("const fallback = 'system'", false);

        Settings::set(['theme.mode' => 'dark']);
        $this->get('/login')->assertSee("const fallback = 'dark'", false);
    }

    public function test_favicon_defaults_to_blue_with_white_stumps(): void
    {
        $svg = app(Theme::class)->favicon();

        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">', $svg);
        $this->assertStringContainsString('fill="#0d6efd"', $svg);
        $this->assertSame(5, substr_count($svg, 'fill="#fff"'));
    }

    public function test_favicon_follows_the_accent(): void
    {
        Settings::set(['theme.accent' => '#6F42C1']);
        $this->assertStringContainsString('fill="#6f42c1"', app(Theme::class)->favicon());

        Settings::set(['theme.accent' => '#ffc107']);
        $this->assertSame(5, substr_count(app(Theme::class)->favicon(), 'fill="#000"'));

        Settings::set(['theme.accent' => 'red;"><script>']);
        $this->assertStringContainsString('fill="#0d6efd"', app(Theme::class)->favicon());
    }

    public function test_head_links_the_svg_favicon_and_ico_fallback(): void
    {
        Settings::set(['theme.accent' => '#198754']);
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="icon" href="/favicon.ico" sizes="32x32">', $html);
        $this->assertStringContainsString('<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,', $html);
        $this->assertStringContainsString(rawurlencode('fill="#198754"'), $html);
        $this->assertStringContainsString('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', $html);
    }
}

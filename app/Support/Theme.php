<?php

namespace App\Support;

/**
 * Settings → Appearance as CSS: Bootstrap variable overrides for the accent colour, corner
 * radius and font, printed after the app stylesheet. Colour maths follows Bootstrap's Sass
 * (tint/shade = mix with white/black, button text by WCAG contrast). Empty at the defaults.
 */
class Theme
{
    public const DEFAULT_ACCENT = '#0d6efd';

    /** Preset accents shown as swatches in Settings. */
    public const PRESETS = [
        'Blue' => '#0d6efd',
        'Indigo' => '#6610f2',
        'Purple' => '#6f42c1',
        'Teal' => '#0f766e',
        'Green' => '#198754',
        'Orange' => '#c2410c',
        'Red' => '#c92a2a',
        'Slate' => '#475569',
    ];

    public const RADII = ['sharp', 'default', 'round'];

    public const FONTS = ['system', 'serif', 'mono'];

    /** Light/dark mode for visitors who haven't used the toggle; system follows their OS. */
    public const MODES = ['system', 'light', 'dark'];

    private const RADIUS_VARS = [
        'sharp' => ['' => '0', 'sm' => '0', 'lg' => '0', 'xl' => '0', 'xxl' => '0'],
        'round' => ['' => '.5rem', 'sm' => '.375rem', 'lg' => '.75rem', 'xl' => '1rem', 'xxl' => '1.5rem'],
    ];

    private const FONT_STACKS = [
        'serif' => 'Georgia, Cambria, "Times New Roman", Times, serif',
        'mono' => 'var(--bs-font-monospace)',
    ];

    public function __construct(private Settings $settings) {}

    public function css(): string
    {
        $accent = strtolower((string) $this->settings->get('theme.accent', self::DEFAULT_ACCENT));
        $radius = $this->settings->get('theme.radius', 'default');
        $font = $this->settings->get('theme.font', 'system');

        $root = [];
        $rules = [];

        if (preg_match('/^#[0-9a-f]{6}$/', $accent) && $accent !== self::DEFAULT_ACCENT) {
            [$root, $rules] = $this->accent($accent);
        }

        foreach (self::RADIUS_VARS[$radius] ?? [] as $size => $value) {
            $root['--bs-border-radius'.($size === '' ? '' : '-'.$size)] = $value;
        }

        if (isset(self::FONT_STACKS[$font])) {
            $root['--bs-body-font-family'] = self::FONT_STACKS[$font];
        }

        if ($root === [] && $rules === []) {
            return '';
        }

        return $this->block('html:root', $root).implode('', $rules);
    }

    /** The stumps-badge favicon as SVG: an accent tile with stumps and bails in the accent's button-text colour. */
    public function favicon(): string
    {
        $accent = strtolower((string) $this->settings->get('theme.accent', self::DEFAULT_ACCENT));
        if (! preg_match('/^#[0-9a-f]{6}$/', $accent)) {
            $accent = self::DEFAULT_ACCENT;
        }
        $on = $this->contrast($this->rgb($accent));

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            ."<rect x=\"1\" y=\"1\" width=\"30\" height=\"30\" rx=\"7\" fill=\"{$accent}\"/>"
            ."<rect x=\"9\" y=\"12\" width=\"3\" height=\"14\" rx=\".8\" fill=\"{$on}\"/>"
            ."<rect x=\"14.5\" y=\"12\" width=\"3\" height=\"14\" rx=\".8\" fill=\"{$on}\"/>"
            ."<rect x=\"20\" y=\"12\" width=\"3\" height=\"14\" rx=\".8\" fill=\"{$on}\"/>"
            ."<rect x=\"8.5\" y=\"8\" width=\"7\" height=\"2.4\" rx=\"1.2\" fill=\"{$on}\"/>"
            ."<rect x=\"16.5\" y=\"8\" width=\"7\" height=\"2.4\" rx=\"1.2\" fill=\"{$on}\"/>"
            .'</svg>';
    }

    /** @return array{0: array<string, string>, 1: list<string>} :root variables, extra rules */
    private function accent(string $hex): array
    {
        $rgb = $this->rgb($hex);
        $on = $this->contrast($rgb);
        $hover = $on === '#fff' ? $this->shade($rgb, .15) : $this->tint($rgb, .15);
        $active = $on === '#fff' ? $this->shade($rgb, .2) : $this->tint($rgb, .2);
        $rgbList = implode(', ', $rgb);
        $darkLink = $this->tint($rgb, .4);

        $root = [
            '--bs-primary' => $hex,
            '--bs-primary-rgb' => $rgbList,
            '--bs-primary-text-emphasis' => $this->shade($rgb, .6),
            '--bs-primary-bg-subtle' => $this->tint($rgb, .8),
            '--bs-primary-border-subtle' => $this->tint($rgb, .6),
            '--bs-link-color' => $hex,
            '--bs-link-color-rgb' => $rgbList,
            '--bs-link-hover-color' => $this->shade($rgb, .2),
            '--bs-link-hover-color-rgb' => implode(', ', $this->rgb($this->shade($rgb, .2))),
            '--bs-focus-ring-color' => "rgba({$rgbList}, .25)",
        ];

        $dark = [
            '--bs-primary-text-emphasis' => $darkLink,
            '--bs-primary-bg-subtle' => $this->shade($rgb, .8),
            '--bs-primary-border-subtle' => $this->shade($rgb, .4),
            '--bs-link-color' => $darkLink,
            '--bs-link-color-rgb' => implode(', ', $this->rgb($darkLink)),
            '--bs-link-hover-color' => $this->tint($rgb, .6),
            '--bs-link-hover-color-rgb' => implode(', ', $this->rgb($this->tint($rgb, .6))),
        ];

        // Bootstrap compiles these component colours as literals, so they need setting again.
        $rules = [
            $this->block('[data-bs-theme=dark]', $dark),
            $this->block('.btn-primary', [
                '--bs-btn-color' => $on, '--bs-btn-bg' => $hex, '--bs-btn-border-color' => $hex,
                '--bs-btn-hover-color' => $on, '--bs-btn-hover-bg' => $hover, '--bs-btn-hover-border-color' => $active,
                '--bs-btn-focus-shadow-rgb' => $rgbList,
                '--bs-btn-active-color' => $on, '--bs-btn-active-bg' => $active, '--bs-btn-active-border-color' => $active,
                '--bs-btn-disabled-color' => $on, '--bs-btn-disabled-bg' => $hex, '--bs-btn-disabled-border-color' => $hex,
            ]),
            $this->block('.btn-outline-primary', [
                '--bs-btn-color' => $hex, '--bs-btn-border-color' => $hex,
                '--bs-btn-hover-color' => $on, '--bs-btn-hover-bg' => $hex, '--bs-btn-hover-border-color' => $hex,
                '--bs-btn-focus-shadow-rgb' => $rgbList,
                '--bs-btn-active-color' => $on, '--bs-btn-active-bg' => $hex, '--bs-btn-active-border-color' => $hex,
                '--bs-btn-disabled-color' => $hex, '--bs-btn-disabled-border-color' => $hex,
            ]),
            $this->block('[data-bs-theme=dark] .btn-outline-primary', ['--bs-btn-color' => $darkLink]),
            $this->block('.text-bg-primary', ['color' => $on.' !important']),
            $this->block('.form-control:focus, .form-select:focus, .form-check-input:focus', [
                'border-color' => $this->tint($rgb, .5),
                'box-shadow' => "0 0 0 .25rem rgba({$rgbList}, .25)",
            ]),
            $this->block('.form-check-input:checked', ['background-color' => $hex, 'border-color' => $hex]),
            $this->block('.dropdown-menu', ['--bs-dropdown-link-active-bg' => $hex, '--bs-dropdown-link-active-color' => $on]),
            $this->block('.pagination', [
                '--bs-pagination-active-bg' => $hex, '--bs-pagination-active-border-color' => $hex,
                '--bs-pagination-active-color' => $on, '--bs-pagination-focus-box-shadow' => "0 0 0 .25rem rgba({$rgbList}, .25)",
            ]),
            $this->block('.nav-pills', ['--bs-nav-pills-link-active-bg' => $hex, '--bs-nav-pills-link-active-color' => $on]),
            $this->block('.list-group', ['--bs-list-group-active-bg' => $hex, '--bs-list-group-active-border-color' => $hex, '--bs-list-group-active-color' => $on]),
            $this->block('.progress, .progress-stacked', ['--bs-progress-bar-bg' => $hex]),
        ];

        return [$root, $rules];
    }

    /** @param  array<string, string>  $declarations */
    private function block(string $selector, array $declarations): string
    {
        if ($declarations === []) {
            return '';
        }

        // "html" outranks Bootstrap's own selectors whichever stylesheet loads last (Vite injects its CSS late in dev).
        $selector = implode(', ', array_map(fn ($s) => str_starts_with($s, 'html') ? $s : 'html'.(str_starts_with($s, '[') ? '' : ' ').$s, explode(', ', $selector)));

        return $selector.' {'.implode('', array_map(fn ($k, $v) => " {$k}: {$v};", array_keys($declarations), $declarations))." }\n";
    }

    /** @return array{int, int, int} */
    private function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    /** @param  array{int, int, int}  $rgb */
    private function hex(array $rgb): string
    {
        return '#'.implode('', array_map(fn ($c) => str_pad(dechex((int) round(max(0, min(255, $c)))), 2, '0', STR_PAD_LEFT), $rgb));
    }

    /** @param  array{int, int, int}  $rgb */
    private function tint(array $rgb, float $weight): string
    {
        return $this->hex(array_map(fn ($c) => $c + (255 - $c) * $weight, $rgb));
    }

    /** @param  array{int, int, int}  $rgb */
    private function shade(array $rgb, float $weight): string
    {
        return $this->hex(array_map(fn ($c) => $c * (1 - $weight), $rgb));
    }

    /**
     * White or black text, as Bootstrap picks it: white unless it falls under 4.5:1
     * (with Bootstrap's rounding, its own blue just passes).
     */
    /** @param  array{int, int, int}  $rgb */
    private function contrast(array $rgb): string
    {
        $luminance = fn (array $c) => array_sum(array_map(
            fn ($v, $w) => $w * (($v /= 255) <= .04045 ? $v / 12.92 : (($v + .055) / 1.055) ** 2.4),
            $c, [.2126, .7152, .0722],
        ));
        $l = $luminance($rgb);

        return 1.05 / ($l + .05) >= 4.45 ? '#fff' : '#000';
    }
}

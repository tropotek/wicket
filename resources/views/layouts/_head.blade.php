{{-- Shared <head> contents for every layout. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', config('app.name'))</title>

<script>
    (() => {
        const stored = localStorage.getItem('wicket.theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const theme = stored ?? (prefersDark ? 'dark' : 'light');
        document.documentElement.dataset.bsTheme = theme;
    })();
</script>

@hasSection('meta')
    @yield('meta')
@else
    @if ($d = App\Facades\Settings::get('meta.description'))
        <meta name="description" content="{{ $d }}">
    @endif
    @if ($k = App\Facades\Settings::get('meta.keywords'))
        <meta name="keywords" content="{{ $k }}">
    @endif
@endif

@vite(['resources/scss/app.scss', 'resources/js/app.js'])
@stack('head')
@if ($themeCss = app(App\Support\Theme::class)->css())
    {{-- Settings → Appearance (validated values only). --}}
    <style id="wk-theme">{!! $themeCss !!}</style>
@endif
@if ($css = App\Facades\Settings::get('custom.css'))
    {{-- Admin-authored (Settings → Custom code); "</" escaped so it can't close the tag. --}}
    <style>{!! str_replace('</', '<\/', $css) !!}</style>
@endif

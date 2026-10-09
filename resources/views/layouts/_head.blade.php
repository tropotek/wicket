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

@vite(['resources/scss/app.scss', 'resources/js/app.js'])
@stack('head')

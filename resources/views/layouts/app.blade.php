<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
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
</head>
<body class="d-flex flex-column min-vh-100"
      hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>

    <nav class="navbar navbar-expand-md border-bottom">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ url('/') }}">
                {{ config('app.name') }}
            </a>
            <div class="ms-auto d-flex align-items-center gap-2"
                 x-data="{
                     theme: document.documentElement.dataset.bsTheme,
                     toggle() {
                         this.theme = this.theme === 'dark' ? 'light' : 'dark';
                         document.documentElement.dataset.bsTheme = this.theme;
                         localStorage.setItem('wicket.theme', this.theme);
                     },
                 }">
                <button class="btn btn-sm btn-outline-secondary"
                        type="button"
                        @click="toggle()"
                        :aria-label="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'">
                    <i class="fa-solid" :class="theme === 'dark' ? 'fa-sun' : 'fa-moon'"></i>
                </button>

                @auth
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user me-1"></i>{{ auth()->user()->name }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{{ route('profile.edit') }}">
                                    <i class="fa-solid fa-id-card me-1"></i>{{ __('Profile') }}
                                </a>
                            </li>
                            @can('admin')
                                <li>
                                    <a class="dropdown-item" href="{{ route('admin.index') }}">
                                        <i class="fa-solid fa-screwdriver-wrench me-1"></i>{{ __('Admin') }}
                                    </a>
                                </li>
                            @endcan
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">
                                        <i class="fa-solid fa-right-from-bracket me-1"></i>{{ __('Log out') }}
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                @else
                    @unless (request()->routeIs('login'))
                        <a class="btn btn-sm btn-primary" href="{{ route('login') }}">{{ __('Log in') }}</a>
                    @endunless
                @endauth
            </div>
        </div>
    </nav>

    <main class="container flex-grow-1 py-4">
        @yield('content')
    </main>

    <footer class="wicket-footer border-top py-3">
        <div class="container d-flex justify-content-between">
            <span>{{ config('app.name') }}</span>
            <span>v{{ config('app.version') }}</span>
        </div>
    </footer>
</body>
</html>

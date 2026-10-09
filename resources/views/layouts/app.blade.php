<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts._head')
</head>
<body class="d-flex flex-column min-vh-100"
      hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>

    <nav class="navbar navbar-expand-md border-bottom">
        <div class="container">
            <a class="navbar-brand fw-semibold" href="{{ url('/') }}">
                {{ config('app.name') }}
            </a>
            @if ($menu->isNotEmpty())
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#main-menu"
                        aria-controls="main-menu" aria-expanded="false" aria-label="{{ __('Toggle menu') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse order-last order-md-0" id="main-menu">
                    <ul class="navbar-nav">
                        @foreach ($menu as $item)
                            @include('menu._item', ['item' => $item, 'level' => 0])
                        @endforeach
                    </ul>
                </div>
            @endif
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

                @can('create', App\Models\Page::class)
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('pages.create') }}">
                        <i class="fa-solid fa-plus me-1"></i>{{ __('New page') }}
                    </a>
                @endcan

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
                            @can('media')
                                <li>
                                    <a class="dropdown-item" href="{{ route('media.index') }}">
                                        <i class="fa-solid fa-photo-film me-1"></i>{{ __('Media') }}
                                    </a>
                                </li>
                            @endcan
                            @if (config('wicket.secrets'))
                                @can('create', App\Models\Secret::class)
                                    <li>
                                        <a class="dropdown-item" href="{{ route('secrets.index') }}">
                                            <i class="fa-solid fa-key me-1"></i>{{ __('Secrets') }}
                                        </a>
                                    </li>
                                @endcan
                            @endif
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
    @stack('editor-scripts')
</body>
</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts._head')
</head>
<body class="d-flex flex-column min-vh-100"
      hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>

    <nav class="navbar navbar-expand-md border-bottom">
        <div class="container">
            <a class="navbar-brand fw-semibold d-flex align-items-center gap-2" href="{{ url('/') }}">
                @if ($logo = App\Facades\Settings::get('theme.logo'))
                    @php($logoText = App\Facades\Settings::get('theme.logo_text'))
                    <img src="{{ app(App\Support\PublicData::class)->url($logo) }}" class="wk-logo" alt="{{ $logoText ? '' : config('app.name') }}">
                    @if ($logoText) {{ config('app.name') }} @endif
                @else
                    {{ config('app.name') }}
                @endif
            </a>
            @if ($menu->isNotEmpty())
                <button class="navbar-toggler order-1 ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#main-menu"
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
            <div class="wk-nav-tools ms-auto d-flex align-items-center"
                 x-data="{
                     theme: document.documentElement.dataset.bsTheme,
                     toggle() {
                         this.theme = this.theme === 'dark' ? 'light' : 'dark';
                         document.documentElement.dataset.bsTheme = this.theme;
                         localStorage.setItem('wicket.theme', this.theme);
                     },
                 }">
                @unless (request()->routeIs('search'))
                    <form method="GET" action="{{ route('search') }}" role="search" class="wk-nav-search d-none d-md-block me-2">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" name="q" class="form-control form-control-sm"
                               placeholder="{{ __('Search pages') }}" aria-label="{{ __('Search pages') }}">
                    </form>
                @endunless
                <ul class="navbar-nav flex-row align-items-center">
                    @unless (request()->routeIs('search'))
                        <li class="nav-item d-md-none">
                            <a class="nav-link" href="{{ route('search') }}" title="{{ __('Search') }}" aria-label="{{ __('Search') }}">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </a>
                        </li>
                    @endunless

                    @can('create', App\Models\Page::class)
                        <li class="nav-item">
                            <a @class(['nav-link', 'active' => request()->routeIs('pages.create')])
                               href="{{ route('pages.create') }}" title="{{ __('New page') }}">
                                <i class="fa-solid fa-plus"></i><span class="d-none d-lg-inline ms-1">{{ __('New page') }}</span>
                            </a>
                        </li>
                    @endcan

                    <li class="nav-item">
                        <button class="nav-link" type="button" @click="toggle()"
                                :title="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'"
                                :aria-label="theme === 'dark' ? 'Switch to light theme' : 'Switch to dark theme'">
                            <i class="fa-solid" :class="theme === 'dark' ? 'fa-sun' : 'fa-moon'"></i>
                        </button>
                    </li>

                    @auth
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false" title="{{ auth()->user()->name }}">
                                @include('users._avatar', ['user' => auth()->user()])
                                <span class="d-none d-lg-inline ms-2">{{ auth()->user()->name }}</span>
                            </a>
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
                        </li>
                    @else
                        @unless (request()->routeIs('login'))
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('login') }}">
                                    <i class="fa-solid fa-right-to-bracket me-1"></i>{{ __('Log in') }}
                                </a>
                            </li>
                        @endunless
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <main class="container flex-grow-1 py-4">
        @yield('content')
    </main>

    <footer class="wicket-footer border-top py-3">
        <div class="container d-flex justify-content-between">
            <span>
                {{ config('app.name') }}
                @if (App\Facades\Settings::get('wiki.contact'))
                    · <a href="{{ route('contact') }}" class="link-secondary">{{ __('Contact') }}</a>
                @endif
            </span>
            <a href="{{ config('wicket.repo_url') }}" class="link-secondary" target="_blank" rel="noopener noreferrer"
               title="{{ __('Wicket on GitHub') }}"><i class="fa-brands fa-github me-1"></i>Wicket v{{ config('app.version') }}</a>
        </div>
    </footer>
    @stack('editor-scripts')
    @if ($js = App\Facades\Settings::get('custom.js'))
        {{-- Admin-authored (Settings → Custom code); runs for every visitor. --}}
        <script>{!! str_replace('</', '<\/', $js) !!}</script>
    @endif
</body>
</html>

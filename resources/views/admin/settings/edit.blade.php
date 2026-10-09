@extends('layouts.app')

@section('title', __('Settings').' · '.config('app.name'))

@php
    // Field name for a setting key ("site.name" → "site__name"), and its current value.
    $f = fn (string $key) => str_replace('.', '__', $key);
    $v = fn (string $key) => old($f($key), $settings[$key]);
@endphp

@section('content')
    <form method="POST" action="{{ route('admin.settings.update') }}" novalidate>
        @csrf
        @method('PUT')

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h1 class="h3 mb-0 me-auto"><i class="fa-solid fa-gear me-2"></i>{{ __('Settings') }}</h1>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>{{ __('Save') }}</button>
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-6">
                <fieldset class="card mb-4"><div class="card-body">
                    <legend class="h5">{{ __('Site') }}</legend>
                    <div class="mb-3">
                        <label for="site__name" class="form-label">{{ __('Site name') }}</label>
                        <input id="site__name" name="site__name" value="{{ $v('site.name') }}" maxlength="100" required
                               class="form-control @error('site__name') is-invalid @enderror">
                        @error('site__name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label for="site__email" class="form-label">{{ __('Site email') }}</label>
                        <input id="site__email" type="email" name="site__email" value="{{ $v('site.email') }}"
                               class="form-control @error('site__email') is-invalid @enderror">
                        <div class="form-text">{{ __('Sender address for mail the wiki sends.') }}</div>
                        @error('site__email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div></fieldset>

                <fieldset class="card mb-4"><div class="card-body">
                    <legend class="h5">{{ __('Wiki') }}</legend>
                    <div class="mb-3">
                        <label for="wiki__home_page" class="form-label">{{ __('Home page') }}</label>
                        <select id="wiki__home_page" name="wiki__home_page" class="form-select @error('wiki__home_page') is-invalid @enderror">
                            <option value="">{{ __('Welcome page (none)') }}</option>
                            @foreach ($pages as $page)
                                <option value="{{ $page->id }}" @selected((string) $v('wiki.home_page') === (string) $page->id)>
                                    {{ $page->title }} (/{{ $page->slug }})
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('Shown at /. Only public pages can be the home page.') }}</div>
                        @error('wiki__home_page') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    @foreach (['wiki.registration' => __('Anyone can register an account'), 'wiki.secrets' => __('Secrets module (stored credentials in pages)'), 'wiki.backlinks' => __('Show "Linked from" under each page'), 'wiki.contact' => __('Contact form (mails the site email)')] as $key => $label)
                        <div class="form-check form-switch">
                            <input type="hidden" name="{{ $f($key) }}" value="0">
                            <input id="{{ $f($key) }}" type="checkbox" name="{{ $f($key) }}" value="1" role="switch" class="form-check-input" @checked($v($key))>
                            <label for="{{ $f($key) }}" class="form-check-label">{{ $label }}</label>
                        </div>
                    @endforeach
                </div></fieldset>

                <fieldset class="card mb-4"><div class="card-body">
                    <legend class="h5">{{ __('Search engines') }}</legend>
                    <p class="small text-body-secondary">{{ __('Used on pages that don\'t set their own.') }}</p>
                    @foreach (['meta.description' => __('Description'), 'meta.keywords' => __('Keywords')] as $key => $label)
                        <div class="mb-3">
                            <label for="{{ $f($key) }}" class="form-label">{{ $label }}</label>
                            <input id="{{ $f($key) }}" name="{{ $f($key) }}" value="{{ $v($key) }}" maxlength="255"
                                   class="form-control @error($f($key)) is-invalid @enderror">
                            @error($f($key)) <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                </div></fieldset>
            </div>

            <div class="col-lg-6">
                <fieldset class="card mb-4" x-data="{ accent: @js($v('theme.accent')) }"><div class="card-body">
                    <legend class="h5">{{ __('Appearance') }}</legend>
                    <div class="mb-3">
                        <span class="form-label d-block" id="accent-label">{{ __('Accent colour') }}</span>
                        <div class="d-flex flex-wrap align-items-center gap-2" role="group" aria-labelledby="accent-label">
                            @foreach (App\Support\Theme::PRESETS as $name => $hex)
                                <button type="button" class="wk-swatch" style="--wk-swatch: {{ $hex }}"
                                        title="{{ __($name) }}" aria-label="{{ __($name) }}"
                                        :class="{ 'active': accent === '{{ $hex }}' }" :aria-pressed="accent === '{{ $hex }}'"
                                        @click="accent = '{{ $hex }}'"></button>
                            @endforeach
                            <input type="color" class="form-control form-control-color" value="{{ $v('theme.accent') }}" x-model="accent"
                                   title="{{ __('Custom colour') }}" aria-label="{{ __('Custom colour') }}">
                            <input type="hidden" name="theme__accent" value="{{ $v('theme.accent') }}" :value="accent">
                        </div>
                        <div class="form-text">{{ __('Buttons, links and highlights, in light and dark mode. Save to apply.') }}</div>
                        @error('theme__accent') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="theme__radius" class="form-label">{{ __('Corners') }}</label>
                            <select id="theme__radius" name="theme__radius" class="form-select @error('theme__radius') is-invalid @enderror">
                                @foreach (['sharp' => __('Sharp'), 'default' => __('Default'), 'round' => __('Rounded')] as $value => $label)
                                    <option value="{{ $value }}" @selected($v('theme.radius') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('theme__radius') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-sm-6">
                            <label for="theme__font" class="form-label">{{ __('Font') }}</label>
                            <select id="theme__font" name="theme__font" class="form-select @error('theme__font') is-invalid @enderror">
                                @foreach (['system' => __('System'), 'serif' => __('Serif'), 'mono' => __('Monospace')] as $value => $label)
                                    <option value="{{ $value }}" @selected($v('theme.font') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('theme__font') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div></fieldset>

                <fieldset class="card mb-4"><div class="card-body">
                    <legend class="h5">{{ __('Maintenance') }}</legend>
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="maintenance__enabled" value="0">
                        <input id="maintenance__enabled" type="checkbox" name="maintenance__enabled" value="1" role="switch" class="form-check-input" @checked($v('maintenance.enabled'))>
                        <label for="maintenance__enabled" class="form-check-label">{{ __('Down for maintenance (admins can still use the site)') }}</label>
                    </div>
                    <label for="maintenance__message" class="form-label">{{ __('Message') }}</label>
                    <textarea id="maintenance__message" name="maintenance__message" rows="3"
                              class="form-control @error('maintenance__message') is-invalid @enderror">{{ $v('maintenance.message') }}</textarea>
                    @error('maintenance__message') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div></fieldset>

                <fieldset class="card mb-4"><div class="card-body">
                    <legend class="h5">{{ __('Custom code') }}</legend>
                    <div class="alert alert-warning small py-2">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('Added to every page for every visitor. JavaScript here runs with full access to the site — only paste code you trust.') }}
                    </div>
                    @foreach (['custom.css' => __('CSS'), 'custom.js' => __('JavaScript')] as $key => $label)
                        <div class="mb-3">
                            <label for="{{ $f($key) }}" class="form-label">{{ $label }}</label>
                            <textarea id="{{ $f($key) }}" name="{{ $f($key) }}" rows="6" spellcheck="false"
                                      class="form-control font-monospace small @error($f($key)) is-invalid @enderror">{{ $v($key) }}</textarea>
                            @error($f($key)) <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    @endforeach
                </div></fieldset>
            </div>
        </div>
    </form>
@endsection

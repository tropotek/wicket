@extends('layouts.app')

@php($editing = $page->exists)

@section('title', ($editing ? __('Edit :title', ['title' => $page->title]) : __('New page')).' · '.config('app.name'))

@push('editor-scripts')
    @vite('resources/js/editor.js')
@endpush

@section('content')
    <form method="POST" novalidate
          action="{{ $editing ? route('pages.update', $page) : route('pages.store') }}"
          x-data="{
              title: @js(old('title', $page->title)),
              slug: @js(old('slug', $page->slug)),
              slugTouched: @js($editing || filled(old('slug'))),
              slugify(s) {
                  return s.normalize('NFKD').replace(/[̀-ͯ]/g, '').toLowerCase()
                      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
              },
          }"
          x-effect="if (! slugTouched) slug = slugify(title)">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <h1 class="h3 mb-0 me-auto">
                <i class="fa-solid {{ $editing ? 'fa-pen-to-square' : 'fa-file-circle-plus' }} me-2"></i>{{ $editing ? __('Edit page') : __('New page') }}
            </h1>
            @if ($editing)
                {{-- Submits #unlock-form (below): releases the edit lock --}}
                <button type="submit" form="unlock-form" class="btn btn-outline-secondary" formnovalidate>{{ __('Cancel') }}</button>
            @else
                <a href="{{ url()->previous(route('home')) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            @endif
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-1"></i>{{ __('Save') }}
            </button>
        </div>

        @if ($editing)
            {{-- Heartbeat: keeps the edit lock; shows a warning here if it was lost --}}
            <div id="lock-status"
                 hx-post="{{ route('pages.lock', $page) }}"
                 hx-trigger="every {{ $heartbeatSeconds }}s"
                 hx-swap="innerHTML"></div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="mb-3">
                    <label for="title" class="form-label">{{ __('Title') }}</label>
                    <input id="title" type="text" name="title" x-model="title" required autofocus
                           class="form-control form-control-lg @error('title') is-invalid @enderror">
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="html" class="form-label">{{ __('Content (HTML)') }}</label>
                    <textarea id="html" name="html" rows="20" spellcheck="true" data-editor
                              data-content-css="{{ Vite::asset('resources/scss/app.scss') }}"
                              data-upload-url="{{ route('media.upload') }}"
                              class="form-control font-monospace @error('html') is-invalid @enderror">{{ old('html', $content?->html) }}</textarea>
                    @error('html')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="slug" class="form-label">{{ __('Slug') }}</label>
                            <div class="input-group">
                                <span class="input-group-text">/</span>
                                <input id="slug" type="text" name="slug" x-model="slug" @input="slugTouched = true"
                                       class="form-control @error('slug') is-invalid @enderror">
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            @if ($editing)
                                <div class="form-text">{{ __('Changing the slug changes the page URL.') }}</div>
                            @else
                                <div class="form-text">{{ __('Filled from the title; edit to override.') }}</div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="visibility" class="form-label">{{ __('Visibility') }}</label>
                            <select id="visibility" name="visibility" class="form-select @error('visibility') is-invalid @enderror">
                                @foreach ($visibilities as $v)
                                    <option value="{{ $v->value }}" @selected(old('visibility', $page->visibility?->value) === $v->value)>{{ $v->label() }}</option>
                                @endforeach
                            </select>
                            @error('visibility')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="category" class="form-label">{{ __('Category') }}</label>
                            <input id="category" type="text" name="category" list="category-list"
                                   value="{{ old('category', $page->category) }}"
                                   class="form-control @error('category') is-invalid @enderror">
                            <datalist id="category-list">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}"></option>
                                @endforeach
                            </datalist>
                            @error('category')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-check form-switch">
                            <input type="hidden" name="publish" value="0">
                            <input id="publish" type="checkbox" name="publish" value="1" role="switch" class="form-check-input"
                                   @checked(old('publish', $page->publish))>
                            <label for="publish" class="form-check-label">{{ __('Published') }}</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input type="hidden" name="title_visible" value="0">
                            <input id="title_visible" type="checkbox" name="title_visible" value="1" role="switch" class="form-check-input"
                                   @checked(old('title_visible', $page->title_visible))>
                            <label for="title_visible" class="form-check-label">{{ __('Show title') }}</label>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <input id="description" type="text" name="description" value="{{ old('description', $content?->description) }}"
                                   class="form-control @error('description') is-invalid @enderror">
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label for="keywords" class="form-label">{{ __('Keywords') }}</label>
                            <input id="keywords" type="text" name="keywords" value="{{ old('keywords', $content?->keywords) }}"
                                   class="form-control @error('keywords') is-invalid @enderror">
                            @error('keywords')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    @if ($editing)
        <form id="unlock-form" method="POST" action="{{ route('pages.unlock', $page) }}" class="d-none">
            @csrf
        </form>
    @endif
@endsection

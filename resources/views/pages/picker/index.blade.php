@extends('layouts.bare')

@section('title', __('Link to a page').' · '.config('app.name'))

@section('content')
    <form class="mb-3" role="search" onsubmit="return false"
          hx-get="{{ route('pages.picker') }}" hx-trigger="input changed delay:300ms from:#picker-q, search from:#picker-q"
          hx-target="#page-picker" hx-swap="outerHTML">
        <input id="picker-q" type="search" name="q" value="{{ $q }}" class="form-control" autofocus
               placeholder="{{ __('Search title, slug or category') }}" aria-label="{{ __('Search') }}">
    </form>

    @include('pages.picker._results')

    {{-- A page that doesn't exist yet: link to its slug; it's created when the link is first followed. --}}
    <form class="border-top pt-3 mt-3" x-data="{ title: @js($q), busy: false, error: '' }"
          @submit.prevent="
              if (! title.trim()) return;
              busy = true; error = '';
              fetch(@js(route('pages.picker.slug')) + '?title=' + encodeURIComponent(title), { headers: { Accept: 'application/json' } })
                  .then((r) => r.ok ? r.json() : Promise.reject(r.status))
                  .then(({ slug }) => window.parent.postMessage({ mceAction: 'wicketPagePick', slug, title: title.trim() }, window.location.origin))
                  .catch(() => error = @js(__('Could not make a link. Try again.')))
                  .finally(() => busy = false)">
        <label for="new-title" class="form-label">{{ __('New page') }}</label>
        <div class="input-group">
            <input id="new-title" class="form-control" x-model="title" maxlength="255"
                   placeholder="{{ __('Title of a page to write later') }}">
            <button type="submit" class="btn btn-outline-primary" :disabled="busy || ! title.trim()">
                <i class="fa-solid fa-plus me-1"></i>{{ __('Insert link') }}
            </button>
        </div>
        <div class="form-text">{{ __('The page is created when someone who can write pages follows the link.') }}</div>
        <div class="text-danger small" x-show="error" x-text="error" x-cloak></div>
    </form>
@endsection

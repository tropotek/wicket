@extends('layouts.bare')

@section('title', __('Insert a secret').' · '.config('app.name'))

@section('content')
    @if ($picked)
        {{-- Just created from this dialog: hand it straight back to the editor. --}}
        <div x-data x-init="window.parent.postMessage({ mceAction: 'wicketSecretPick', id: {{ $picked->id }}, name: @js($picked->name) }, window.location.origin)"
             class="text-body-secondary">{{ __('Inserting ":name"…', ['name' => $picked->name]) }}</div>
    @endif

    <div class="d-flex gap-2 mb-3">
        <form class="flex-grow-1" role="search" onsubmit="return false"
              hx-get="{{ route('secrets.picker') }}" hx-trigger="input changed delay:300ms from:#picker-q, search from:#picker-q"
              hx-target="#secret-picker" hx-swap="outerHTML">
            <input id="picker-q" type="search" name="q" value="{{ $q }}" class="form-control" autofocus
                   placeholder="{{ __('Search secrets') }}" aria-label="{{ __('Search') }}">
        </form>
        @can('create', App\Models\Secret::class)
            <a href="{{ route('secrets.create', ['picker' => 1]) }}" class="btn btn-outline-primary text-nowrap">
                <i class="fa-solid fa-plus me-1"></i>{{ __('New secret') }}
            </a>
        @endcan
    </div>

    @include('secrets.picker._results')
@endsection

@extends('layouts.app')

@section('title', ($q !== '' ? $q.' · ' : '').__('Search').' · '.config('app.name'))

@section('content')
    <h1 class="h3 mb-3"><i class="fa-solid fa-magnifying-glass me-2"></i>{{ __('Search') }}</h1>

    <form method="GET" action="{{ route('search') }}" role="search" class="mb-4"
          hx-get="{{ route('search') }}" hx-trigger="input changed delay:300ms from:#search-q, search from:#search-q"
          hx-target="#search-results" hx-swap="outerHTML" hx-push-url="true">
        <div class="input-group input-group-lg">
            <input id="search-q" type="search" name="q" value="{{ $q }}" class="form-control" autofocus autocomplete="off"
                   placeholder="{{ __('Search pages') }}" aria-label="{{ __('Search pages') }}">
            <button type="submit" class="btn btn-primary" aria-label="{{ __('Search') }}"><i class="fa-solid fa-magnifying-glass"></i></button>
        </div>
    </form>

    @include('search._results')
@endsection

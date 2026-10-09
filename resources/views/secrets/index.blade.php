@extends('layouts.app')

@section('title', __('Secrets').' · '.config('app.name'))

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0 me-auto"><i class="fa-solid fa-key me-2"></i>{{ __('Secrets') }}</h1>
        <a href="{{ route('secrets.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>{{ __('New secret') }}
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <form method="GET" action="{{ route('secrets.index') }}" class="row g-2 mb-3" role="search"
          hx-get="{{ route('secrets.index') }}"
          hx-trigger="change, keyup changed delay:300ms from:input[name=q]"
          hx-target="#secret-table"
          hx-swap="outerHTML"
          hx-push-url="true">
        <div class="col-md-6">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control"
                   placeholder="{{ __('Search names') }}" aria-label="{{ __('Search') }}">
        </div>
        <div class="col-6 col-md-3">
            <select name="visibility" class="form-select" aria-label="{{ __('Visibility') }}">
                <option value="">{{ __('Any visibility') }}</option>
                @foreach ($visibilities as $v)
                    <option value="{{ $v->value }}" @selected(($filters['visibility'] ?? '') === $v->value)>{{ $v->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <select name="otp" class="form-select" aria-label="{{ __('2FA') }}">
                <option value="">{{ __('With or without 2FA') }}</option>
                <option value="yes" @selected(($filters['otp'] ?? '') === 'yes')>{{ __('Has 2FA') }}</option>
                <option value="no" @selected(($filters['otp'] ?? '') === 'no')>{{ __('No 2FA') }}</option>
            </select>
        </div>
        <noscript><div class="col-12"><button class="btn btn-secondary">{{ __('Filter') }}</button></div></noscript>
    </form>

    @include('secrets._table')
@endsection

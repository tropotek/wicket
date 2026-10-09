@extends('layouts.app')

@section('title', __('Users').' · '.config('app.name'))

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0 me-auto"><i class="fa-solid fa-users me-2"></i>{{ __('Users') }}</h1>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-user-plus me-1"></i>{{ __('New user') }}
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.users.index') }}" class="mb-3" role="search">
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="search" name="q" value="{{ $q }}" class="form-control"
                   placeholder="{{ __('Search username, name or email') }}" aria-label="{{ __('Search') }}"
                   hx-get="{{ route('admin.users.index') }}"
                   hx-trigger="input changed delay:300ms, search"
                   hx-target="#user-table"
                   hx-swap="outerHTML"
                   hx-push-url="true">
        </div>
    </form>

    @include('admin.users._table')
@endsection

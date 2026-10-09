@extends('layouts.app')

@section('title', __('Admin').' · '.config('app.name'))

@section('content')
    <h1 class="h3 mb-4"><i class="fa-solid fa-screwdriver-wrench me-2"></i>{{ __('Admin') }}</h1>

    <div class="list-group">
        @can('viewAny', App\Models\User::class)
            <a href="{{ route('admin.users.index') }}" class="list-group-item list-group-item-action">
                <i class="fa-solid fa-users me-2"></i>{{ __('Users') }}
            </a>
        @endcan
        <a href="{{ route('admin.pages.index') }}" class="list-group-item list-group-item-action">
            <i class="fa-solid fa-file-lines me-2"></i>{{ __('Pages') }}
        </a>
        <div class="list-group-item text-body-secondary">
            <i class="fa-solid fa-gear me-2"></i>{{ __('Settings') }}
            <span class="badge text-bg-secondary ms-2">{{ __('coming soon') }}</span>
        </div>
    </div>
@endsection

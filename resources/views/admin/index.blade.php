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
        <a href="{{ route('admin.links.index') }}" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="fa-solid fa-link-slash me-2"></i>{{ __('Broken links') }}
            @php($brokenPages = App\Models\PageLink::broken()->distinct()->count('page_id'))
            @if ($brokenPages)
                <span class="badge text-bg-danger ms-auto">{{ $brokenPages }}</span>
            @endif
        </a>
        <a href="{{ route('admin.menu.index') }}" class="list-group-item list-group-item-action">
            <i class="fa-solid fa-bars me-2"></i>{{ __('Menu') }}
        </a>
        <a href="{{ route('media.index') }}" class="list-group-item list-group-item-action">
            <i class="fa-solid fa-photo-film me-2"></i>{{ __('Media') }}
        </a>
        @if (config('wicket.secrets'))
            <a href="{{ route('secrets.index') }}" class="list-group-item list-group-item-action">
                <i class="fa-solid fa-key me-2"></i>{{ __('Secrets') }}
            </a>
        @endif
        <a href="{{ route('admin.settings.edit') }}" class="list-group-item list-group-item-action">
            <i class="fa-solid fa-gear me-2"></i>{{ __('Settings') }}
        </a>
    </div>
@endsection

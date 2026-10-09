@extends('layouts.app')

@section('title', $page->title.' · '.config('app.name'))

@push('head')
    @if ($content?->description)
        <meta name="description" content="{{ $content->description }}">
    @endif
    @if ($content?->keywords)
        <meta name="keywords" content="{{ $content->keywords }}">
    @endif
@endpush

@section('content')
    <article class="wk-page">
        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
            </div>
        @endif

        <header class="d-flex flex-wrap align-items-center gap-2 mb-3">
            @if ($page->title_visible)
                <h1 class="h2 mb-0">{{ $page->title }}</h1>
            @endif
            @can('update', $page)
                @if ($lock)
                    <span class="badge text-bg-info" title="{{ __('Lock frees :when', ['when' => $lock->expires_at->diffForHumans()]) }}">
                        <i class="fa-solid fa-lock me-1"></i>{{ __('Being edited by :name', ['name' => $lock->user->name]) }}
                    </span>
                @endif
                @unless ($page->publish)
                    <span class="badge text-bg-warning">{{ __('Unpublished') }}</span>
                @endunless
                <div class="btn-group btn-group-sm ms-auto">
                    <a href="{{ route('pages.history', $page) }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i>{{ __('History') }}
                    </a>
                    <a href="{{ route('pages.edit', $page) }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-pen me-1"></i>{{ __('Edit') }}
                    </a>
                </div>
            @endcan
        </header>

        @include('pages._content')

        @if ($content)
            <footer class="wk-page-meta border-top mt-4 pt-2 small text-body-secondary">
                {{ __('Updated') }}
                <time datetime="{{ $content->created_at->toIso8601String() }}" title="{{ $content->created_at->toDayDateTimeString() }}">
                    {{ $content->created_at->diffForHumans() }}
                </time>
                @if ($content->user)
                    {{ __('by :name', ['name' => $content->user->name]) }}
                @endif
            </footer>
        @endif
    </article>
@endsection

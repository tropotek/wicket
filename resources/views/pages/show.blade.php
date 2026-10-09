@extends('layouts.app')

@section('title', $page->title.' · '.config('app.name'))

@if ($content?->description || $content?->keywords)
    @section('meta')
        @if ($content->description)
            <meta name="description" content="{{ $content->description }}">
        @endif
        @if ($content->keywords)
            <meta name="keywords" content="{{ $content->keywords }}">
        @endif
    @endsection
@endif

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

        @if ($backlinkCount)
            <aside class="wk-backlinks small mt-3" aria-label="{{ __('Pages linking here') }}">
                <span class="text-body-secondary">
                    <i class="fa-solid fa-link me-1"></i>{{ trans_choice('Linked from :count page:|Linked from :count pages:', $backlinkCount) }}
                </span>
                @foreach ($backlinks as $link)
                    <a href="{{ route('pages.show', $link) }}" class="ms-1">{{ $link->title }}</a>@unless ($loop->last),@endunless
                @endforeach
                @if ($backlinkCount > $backlinks->count())
                    <span class="text-body-secondary">{{ __('and :count more', ['count' => $backlinkCount - $backlinks->count()]) }}</span>
                @endif
            </aside>
        @endif
    </article>
@endsection

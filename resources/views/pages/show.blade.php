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
        @if ($page->title_visible || ! $page->publish)
            <header class="d-flex flex-wrap align-items-center gap-2 mb-3">
                @if ($page->title_visible)
                    <h1 class="h2 mb-0 me-auto">{{ $page->title }}</h1>
                @endif
                @can('update', $page)
                    @unless ($page->publish)
                        <span class="badge text-bg-warning">{{ __('Unpublished') }}</span>
                    @endunless
                @endcan
            </header>
        @endif

        <div class="wk-content">
            {{-- Editor HTML; sanitised on save (task 90) --}}
            {!! $content?->html !!}
        </div>

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

@extends('layouts.app')

@section('title', __(':title (revision #:n)', ['title' => $page->title, 'n' => $number]).' · '.config('app.name'))

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('pages.show', $page) }}">{{ $page->title }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('pages.history', $page) }}">{{ __('History') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">#{{ $number }}</li>
        </ol>
    </nav>

    @if (session('status'))
        <div class="alert alert-info">{{ session('status') }}</div>
    @endif

    <div class="alert {{ $isCurrent ? 'alert-success' : 'alert-warning' }} d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <i class="fa-solid fa-clock-rotate-left me-1"></i>
            {{ __('Revision #:n, saved :when by :who.', [
                'n' => $number,
                'when' => $content->created_at->toDayDateTimeString(),
                'who' => $content->user?->name ?? __('unknown'),
            ]) }}
            @if ($isCurrent)
                {{ __('This is the current revision.') }}
            @endif
        </div>
        @unless ($isCurrent)
            <form method="POST" action="{{ route('pages.restore', [$page, $content]) }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-warning">
                    <i class="fa-solid fa-rotate-left me-1"></i>{{ __('Restore this revision') }}
                </button>
            </form>
        @endunless
    </div>

    <article class="wk-page">
        @if ($page->title_visible)
            <h1 class="h2 mb-3">{{ $page->title }}</h1>
        @endif
        <div class="wk-content">
            {!! $content->html !!}
        </div>
    </article>
@endsection

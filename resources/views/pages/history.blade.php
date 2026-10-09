@extends('layouts.app')

@section('title', __('History: :title', ['title' => $page->title]).' · '.config('app.name'))

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('pages.show', $page) }}">{{ $page->title }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('History') }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-4"><i class="fa-solid fa-clock-rotate-left me-2"></i>{{ __('History') }}</h1>

    <form method="GET" action="{{ route('pages.diff', $page) }}"
          x-data="{ from: @js($revisions->skip(1)->first()?->id), to: @js($currentId) }">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th class="text-center" title="{{ __('Compare from') }}">{{ __('From') }}</th>
                        <th class="text-center" title="{{ __('Compare to') }}">{{ __('To') }}</th>
                        <th>{{ __('Revision') }}</th>
                        <th>{{ __('Saved') }}</th>
                        <th>{{ __('By') }}</th>
                        <th class="text-end d-none d-md-table-cell">{{ __('Size') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($revisions as $number => $rev)
                        <tr>
                            <td class="text-center">
                                <input type="radio" name="from" value="{{ $rev->id }}" class="form-check-input"
                                       x-model.number="from" aria-label="{{ __('Compare from #:n', ['n' => $number]) }}">
                            </td>
                            <td class="text-center">
                                <input type="radio" name="to" value="{{ $rev->id }}" class="form-check-input"
                                       x-model.number="to" aria-label="{{ __('Compare to #:n', ['n' => $number]) }}">
                            </td>
                            <td>
                                <a href="{{ route('pages.revision', [$page, $rev]) }}" class="fw-semibold">#{{ $number }}</a>
                                @if ($rev->id === $currentId)
                                    <span class="badge text-bg-success ms-1">{{ __('current') }}</span>
                                @endif
                            </td>
                            <td>
                                <time datetime="{{ $rev->created_at->toIso8601String() }}" title="{{ $rev->created_at->toDayDateTimeString() }}">
                                    {{ $rev->created_at->diffForHumans() }}
                                </time>
                            </td>
                            <td>{{ $rev->user?->name ?? __('unknown') }}</td>
                            <td class="text-end d-none d-md-table-cell text-body-secondary small">{{ Number::fileSize(strlen($rev->html)) }}</td>
                            <td class="text-end text-nowrap">
                                @if (! $loop->last)
                                    @php($previous = $revisions->get($number - 1))
                                    <a href="{{ route('pages.diff', [$page, 'from' => $previous->id, 'to' => $rev->id]) }}"
                                       class="btn btn-sm btn-outline-secondary">{{ __('Changes') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($revisions->count() > 1)
            <button type="submit" class="btn btn-primary" :disabled="from === to">
                <i class="fa-solid fa-code-compare me-1"></i>{{ __('Compare selected') }}
            </button>
        @endif
    </form>
@endsection

@extends('layouts.app')

@section('title', __('Changes: :title', ['title' => $page->title]).' · '.config('app.name'))

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('pages.show', $page) }}">{{ $page->title }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('pages.history', $page) }}">{{ __('History') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('Changes') }}</li>
        </ol>
    </nav>

    <h1 class="h3 mb-3">
        <i class="fa-solid fa-code-compare me-2"></i>
        <a href="{{ route('pages.revision', [$page, $from]) }}">#{{ $numbers[$from->id] }}</a>
        <i class="fa-solid fa-arrow-right mx-1 small"></i>
        <a href="{{ route('pages.revision', [$page, $to]) }}">#{{ $numbers[$to->id] }}</a>
    </h1>
    <p class="text-body-secondary small">
        {{ $from->user?->name ?? __('unknown') }}, {{ $from->created_at->toDayDateTimeString() }}
        → {{ $to->user?->name ?? __('unknown') }}, {{ $to->created_at->toDayDateTimeString() }}
    </p>

    @foreach (['description' => __('Description'), 'keywords' => __('Keywords')] as $field => $label)
        @if ($from->{$field} !== $to->{$field})
            <dl class="row small mb-2">
                <dt class="col-sm-2">{{ $label }}</dt>
                <dd class="col-sm-10 mb-0">
                    <del class="text-danger">{{ $from->{$field} ?: '—' }}</del>
                    → <ins class="text-success">{{ $to->{$field} ?: '—' }}</ins>
                </dd>
            </dl>
        @endif
    @endforeach

    @if (! $changed)
        <div class="alert alert-secondary">{{ __('The content of these revisions is identical.') }}</div>
    @else
        <div class="table-responsive border rounded">
            <table class="table table-sm mb-0 font-monospace small wk-diff">
                <tbody>
                    @foreach ($rows as $row)
                        @if ($row['type'] === 'skip')
                            <tr class="table-light">
                                <td class="text-center text-body-secondary" colspan="2">
                                    <i class="fa-solid fa-ellipsis me-1"></i>{{ trans_choice(':count unchanged line|:count unchanged lines', $row['count']) }}
                                </td>
                            </tr>
                        @else
                            <tr @class(['table-success' => $row['type'] === 'added', 'table-danger' => $row['type'] === 'removed'])>
                                <td class="text-center text-body-secondary user-select-none" style="width: 2rem">
                                    {{ ['added' => '+', 'removed' => '−', 'same' => ''][$row['type']] }}
                                </td>
                                <td class="text-break" style="white-space: pre-wrap">{{ $row['text'] }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

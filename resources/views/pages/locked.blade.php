@extends('layouts.app')

@section('title', __('Page locked').' · '.config('app.name'))

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card shadow-sm border-warning">
                <div class="card-body p-4">
                    <h1 class="h4"><i class="fa-solid fa-lock text-warning me-2"></i>{{ __('Page locked') }}</h1>
                    <p class="mb-1">
                        {!! __(':name is editing <strong>:title</strong> right now.', [
                            'name' => e($lock?->user?->name ?? __('Someone')),
                            'title' => e($page->title),
                        ]) !!}
                    </p>
                    @if ($lock)
                        <p class="text-body-secondary small">
                            {{ __('The lock frees up :when unless they keep editing.', ['when' => $lock->expires_at->diffForHumans()]) }}
                        </p>
                    @endif
                    <div class="d-flex gap-2 mt-3">
                        <a href="{{ route('pages.show', $page) }}" class="btn btn-outline-secondary">{{ __('Back to page') }}</a>
                        <a href="{{ route('pages.edit', $page) }}" class="btn btn-primary">
                            <i class="fa-solid fa-rotate-right me-1"></i>{{ __('Try again') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

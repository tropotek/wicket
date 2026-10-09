@extends('layouts.app')

@section('title', __('Pages').' · '.config('app.name'))

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0 me-auto"><i class="fa-solid fa-file-lines me-2"></i>{{ __('Pages') }}</h1>
        <a href="{{ route('pages.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>{{ __('New page') }}
        </a>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <form method="GET" action="{{ route('admin.pages.index') }}" class="row g-2 mb-3" role="search"
          hx-get="{{ route('admin.pages.index') }}"
          hx-trigger="change, keyup changed delay:300ms from:input[name=q]"
          hx-target="#page-table"
          hx-swap="outerHTML"
          hx-push-url="true">
        <div class="col-md-4">
            <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control"
                   placeholder="{{ __('Search title or slug') }}" aria-label="{{ __('Search') }}">
        </div>
        <div class="col-6 col-md-2">
            <select name="category" class="form-select" aria-label="{{ __('Category') }}">
                <option value="">{{ __('Any category') }}</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="visibility" class="form-select" aria-label="{{ __('Visibility') }}">
                <option value="">{{ __('Any visibility') }}</option>
                @foreach ($visibilities as $v)
                    <option value="{{ $v->value }}" @selected(($filters['visibility'] ?? '') === $v->value)>{{ $v->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="publish" class="form-select" aria-label="{{ __('Published') }}">
                <option value="">{{ __('Published or not') }}</option>
                <option value="yes" @selected(($filters['publish'] ?? '') === 'yes')>{{ __('Published') }}</option>
                <option value="no" @selected(($filters['publish'] ?? '') === 'no')>{{ __('Unpublished') }}</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <select name="status" class="form-select" aria-label="{{ __('Status') }}">
                <option value="active">{{ __('Live pages') }}</option>
                <option value="trash" @selected($trash)>{{ __('Trash') }}</option>
            </select>
        </div>
        <noscript><div class="col-12"><button class="btn btn-secondary">{{ __('Filter') }}</button></div></noscript>
    </form>

    @include('admin.pages._table')
@endsection

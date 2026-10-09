@extends('layouts.app')

@section('title', __('Page not found').' · '.config('app.name'))

@section('content')
    <div class="py-4">
        <h1 class="h3"><i class="fa-solid fa-file-circle-question me-2"></i>{{ __('This page doesn\'t exist yet') }}</h1>
        <p class="text-body-secondary">{{ __('Nothing has been written at /:slug.', ['slug' => $slug]) }}</p>
        <a href="{{ route('pages.create', ['title' => $title, 'slug' => $slug]) }}" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i>{{ __('Create this page') }}
        </a>
    </div>
@endsection

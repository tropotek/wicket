@extends('layouts.app')

@section('title', __('Menu').' · '.config('app.name'))

@push('head')
    @vite('resources/js/menu-editor.js')
    <style>
        .menu-children { min-height: .75rem; }
        .menu-row.sortable-ghost > div { opacity: .4; }
    </style>
@endpush

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0 me-auto"><i class="fa-solid fa-bars me-2"></i>{{ __('Menu') }}</h1>
    </div>

    <p class="text-body-secondary">
        {{ __('Drag rows to reorder. Drop links and dividers into a dropdown to nest them. Changes save as you go.') }}
    </p>

    @include('admin.menu._editor', ['status' => session('status')])
@endsection

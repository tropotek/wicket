@extends('layouts.app')

@section('title', $secret->name.' · '.config('app.name'))

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0 me-auto"><i class="fa-solid fa-key me-2"></i>{{ $secret->name }}</h1>
        <a href="{{ route('secrets.index') }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
    </div>

    <x-secret-card :secret="$secret" />
@endsection

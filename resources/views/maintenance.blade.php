@extends('layouts.bare')

@section('title', __('Down for maintenance').' · '.config('app.name'))

@section('content')
    <div class="container py-5 text-center" style="max-width: 40rem">
        <i class="fa-solid fa-screwdriver-wrench fa-3x text-body-secondary mb-3"></i>
        <h1 class="h3">{{ config('app.name') }} {{ __('is down for maintenance') }}</h1>
        <p class="text-body-secondary" style="white-space: pre-line">{{ $message ?: __('Back soon.') }}</p>
        <a href="{{ route('login') }}" class="small">{{ __('Admin sign in') }}</a>
    </div>
@endsection

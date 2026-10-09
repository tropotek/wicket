@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <div class="py-5">
        <h1 class="display-5 fw-semibold">{{ config('app.name') }}</h1>
        <p class="lead text-body-secondary">A fresh wicket, waiting for its first page.</p>
    </div>
@endsection

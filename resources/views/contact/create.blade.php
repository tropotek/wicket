@extends('layouts.app')

@section('title', __('Contact').' · '.config('app.name'))

@section('content')
    <div style="max-width: 44rem">
        <h1 class="h3 mb-3"><i class="fa-solid fa-envelope me-2"></i>{{ __('Contact us') }}</h1>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('contact.send') }}" novalidate>
            @csrf
            <input type="hidden" name="stamp" value="{{ $stamp }}">
            {{-- Honeypot: hidden from people, irresistible to bots. --}}
            <div class="position-absolute" style="left: -10000px" aria-hidden="true">
                <label for="website">{{ __('Website') }}</label>
                <input id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="row g-3 mb-3">
                @foreach ([['name', __('Name'), 'text', 'name'], ['email', __('Email'), 'email', 'email'], ['phone', __('Phone (optional)'), 'tel', 'tel']] as [$field, $label, $type, $auto])
                    <div class="col-md-4">
                        <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                        <input id="{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" autocomplete="{{ $auto }}"
                               @if ($field !== 'phone') required @endif
                               class="form-control @error($field) is-invalid @enderror">
                        @error($field)
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach
            </div>
            <div class="mb-3">
                <label for="message" class="form-label">{{ __('Message') }}</label>
                <textarea id="message" name="message" rows="8" maxlength="5000" required
                          class="form-control @error('message') is-invalid @enderror">{{ old('message') }}</textarea>
                @error('message')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>{{ __('Send') }}</button>
        </form>
    </div>
@endsection

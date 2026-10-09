@extends($picker ? 'layouts.bare' : 'layouts.app')

@section('title', __('Media').' · '.config('app.name'))

@section('content')
    @unless ($picker)
        <h1 class="h3 mb-3"><i class="fa-solid fa-photo-film me-2"></i>{{ __('Media') }}</h1>
    @endunless

    @include('media._browser', ['status' => session('status')])
@endsection

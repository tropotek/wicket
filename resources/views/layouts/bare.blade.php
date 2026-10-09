<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts._head')
</head>
{{-- No navbar or footer: for pages shown inside an editor dialog. --}}
<body hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
    <main class="container-fluid py-3">
        @yield('content')
    </main>
</body>
</html>

Message from the {{ config('app.name') }} contact form.

Name:  {{ $data['name'] }}
Email: {{ $data['email'] }}
@if (! empty($data['phone']))
Phone: {{ $data['phone'] }}
@endif

{{ $data['message'] }}

--
Reply to this email to answer {{ $data['name'] }}.

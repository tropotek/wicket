<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $page->title }}</title>
    <style>
        @page { margin: 18mm 14mm 20mm; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 10.5pt; line-height: 1.45; color: #222; }
        h1 { font-size: 18pt; margin: 0 0 2mm; }
        .meta { color: #777; font-size: 8.5pt; border-bottom: 0.4pt solid #bbb; padding-bottom: 2mm; margin-bottom: 5mm; }
        h2 { font-size: 14pt; } h3 { font-size: 12pt; }
        a { color: #0b5ed7; text-decoration: none; }
        img { max-width: 100%; height: auto; }
        table { border-collapse: collapse; width: 100%; margin: 3mm 0; }
        td, th { border: 0.4pt solid #999; padding: 1.5mm 2mm; vertical-align: top; }
        th { background: #eee; }
        pre { font-family: "DejaVu Sans Mono", monospace; font-size: 8.5pt; background: #f4f4f4; padding: 2mm; white-space: pre-wrap; word-wrap: break-word; }
        code { font-family: "DejaVu Sans Mono", monospace; font-size: 9pt; }
        blockquote { border-left: 1pt solid #bbb; margin-left: 0; padding-left: 4mm; color: #555; }
        .wk-secret-pdf { font-family: "DejaVu Sans Mono", monospace; font-size: 9pt; background: #eee; padding: 0 1mm; }
    </style>
</head>
<body>
    @if ($page->title_visible)
        <h1>{{ $page->title }}</h1>
    @endif
    <div class="meta">
        {{ url($page->slug) }} · {{ __('Printed :date', ['date' => now()->toDayDateTimeString()]) }}
    </div>
    {!! $html !!}
</body>
</html>

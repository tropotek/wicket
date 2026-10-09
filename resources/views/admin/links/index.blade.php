@extends('layouts.app')

@section('title', __('Broken links').' · '.config('app.name'))

@section('content')
    <h1 class="h3 mb-3"><i class="fa-solid fa-link-slash me-2"></i>{{ __('Broken links') }}</h1>

    @if ($pages->isEmpty())
        <p class="text-body-secondary">{{ __('Every page link points at a page that exists.') }}</p>
    @else
        <p class="text-body-secondary">{{ __('Pages linking to pages that don\'t exist (yet). Create the missing page, or edit the link.') }}</p>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Page') }}</th>
                        <th>{{ __('Links to missing') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td><a href="{{ route('pages.show', $page) }}">{{ $page->title }}</a></td>
                            <td>
                                @foreach ($broken[$page->id] as $link)
                                    <a href="{{ route('pages.create', ['title' => Str::of($link->slug)->replace(['-', '_'], ' ')->ucfirst()->toString(), 'slug' => $link->slug]) }}"
                                       class="badge text-bg-danger text-decoration-none me-1" title="{{ __('Create this page') }}">/{{ $link->slug }}</a>
                                @endforeach
                            </td>
                            <td class="text-end">
                                <a href="{{ route('pages.edit', $page) }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa-solid fa-pen me-1"></i>{{ __('Edit') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

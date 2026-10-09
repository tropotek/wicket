<div id="search-results" hx-boost="true" hx-target="#search-results" hx-swap="outerHTML" hx-push-url="true">
    @if (! $searched)
        <p class="text-body-secondary">{{ __('Type a word or two. Partial words match too.') }}</p>
    @elseif ($results->isEmpty())
        <p class="text-body-secondary">{{ __('No pages match ":q".', ['q' => $q]) }}</p>
    @else
        <p class="small text-body-secondary">{{ trans_choice(':count page found|:count pages found', $results->total()) }}</p>
        <ol class="list-unstyled">
            @foreach ($results as $page)
                <li class="mb-3">
                    <a href="{{ route('pages.show', $page) }}" class="fs-5" hx-boost="false">{{ $page->title }}</a>
                    @if ($page->category !== '')
                        <span class="badge text-bg-light border ms-1">{{ $page->category }}</span>
                    @endif
                    @unless ($page->publish)
                        <span class="badge text-bg-warning ms-1">{{ __('Unpublished') }}</span>
                    @endunless
                    @if ($page->snippet_html !== '')
                        {{-- Escaped in SearchController; only <mark> is markup. --}}
                        <div class="text-body-secondary">{!! $page->snippet_html !!}</div>
                    @endif
                    <div class="small text-body-tertiary">
                        /{{ $page->slug }}
                        @if ($page->latestContent)
                            · {{ __('updated :when', ['when' => $page->latestContent->created_at->diffForHumans()]) }}
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
        {{ $results->links() }}
    @endif
</div>

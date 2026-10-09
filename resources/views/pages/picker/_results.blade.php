<div id="page-picker" hx-target="#page-picker" hx-swap="outerHTML" x-data>
    @if ($pages->isEmpty())
        <p class="text-body-secondary">{{ $q === '' ? __('There are no pages yet.') : __('No pages match ":q".', ['q' => $q]) }}</p>
    @else
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-2">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Slug') }}</th>
                        <th class="d-none d-sm-table-cell">{{ __('Category') }}</th>
                        <th class="d-none d-sm-table-cell">{{ __('Visibility') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td>
                                {{ $page->title }}
                                @unless ($page->publish)
                                    <span class="badge text-bg-warning ms-1">{{ __('Unpublished') }}</span>
                                @endunless
                            </td>
                            <td class="text-body-secondary">/{{ $page->slug }}</td>
                            <td class="d-none d-sm-table-cell">{{ $page->category }}</td>
                            <td class="d-none d-sm-table-cell"><span class="badge text-bg-secondary">{{ $page->visibility->label() }}</span></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-primary"
                                        @click="window.parent.postMessage({ mceAction: 'wicketPagePick', slug: @js($page->slug), title: @js($page->title) }, window.location.origin)">
                                    {{ __('Insert') }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{-- Paging links swap just the results. --}}
        <div hx-boost="true">{{ $pages->links() }}</div>
    @endif
</div>

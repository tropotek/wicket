<div id="page-table"
     x-data="{ selected: [] }"
     hx-boost="true" hx-target="#page-table" hx-swap="outerHTML" hx-push-url="true">
    <form method="POST" action="{{ route('admin.pages.bulk') }}" hx-boost="false"
          @submit="if (! confirm($event.submitter.dataset.confirm.replace(':count', selected.length))) $event.preventDefault()">
        @csrf

        <div class="d-flex flex-wrap align-items-center gap-2 mb-2" x-show="selected.length" x-cloak>
            <span class="small text-body-secondary me-2" x-text="selected.length + ' {{ __('selected') }}'"></span>
            @if ($trash)
                <button type="submit" name="action" value="restore" class="btn btn-sm btn-outline-success"
                        data-confirm="{{ __('Restore :count page(s)?') }}">
                    <i class="fa-solid fa-trash-arrow-up me-1"></i>{{ __('Restore') }}
                </button>
                <button type="submit" name="action" value="purge" class="btn btn-sm btn-danger"
                        data-confirm="{{ __('Permanently delete :count page(s) and all their revisions? This cannot be undone.') }}">
                    <i class="fa-solid fa-trash me-1"></i>{{ __('Delete permanently') }}
                </button>
            @else
                <button type="submit" name="action" value="trash" class="btn btn-sm btn-outline-danger"
                        data-confirm="{{ __('Move :count page(s) to the trash?') }}">
                    <i class="fa-solid fa-trash-can me-1"></i>{{ __('Move to trash') }}
                </button>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 2rem">
                            <input type="checkbox" class="form-check-input" aria-label="{{ __('Select all') }}"
                                   :checked="selected.length && selected.length === {{ $pages->count() }}"
                                   @change="selected = $event.target.checked ? @js($pages->pluck('id')) : []">
                        </th>
                        <th>{{ __('Title') }}</th>
                        <th class="d-none d-md-table-cell">{{ __('Slug') }}</th>
                        <th class="d-none d-lg-table-cell">{{ __('Category') }}</th>
                        <th>{{ __('Visibility') }}</th>
                        <th class="d-none d-md-table-cell text-end">{{ __('Views') }}</th>
                        <th class="d-none d-lg-table-cell">{{ __('Author') }}</th>
                        <th class="d-none d-lg-table-cell">{{ $trash ? __('Deleted') : __('Updated') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pages as $page)
                        <tr>
                            <td>
                                <input type="checkbox" name="ids[]" value="{{ $page->id }}" class="form-check-input"
                                       x-model.number="selected" aria-label="{{ __('Select :title', ['title' => $page->title]) }}">
                            </td>
                            <td>
                                @if ($trash)
                                    <span class="fw-semibold">{{ $page->title }}</span>
                                @else
                                    <a href="{{ route('pages.show', $page) }}" hx-boost="false" class="fw-semibold">{{ $page->title }}</a>
                                @endif
                                @unless ($page->publish)
                                    <span class="badge text-bg-warning ms-1">{{ __('Unpublished') }}</span>
                                @endunless
                            </td>
                            <td class="d-none d-md-table-cell text-body-secondary small">/{{ $page->slug }}</td>
                            <td class="d-none d-lg-table-cell">{{ $page->category }}</td>
                            <td><span class="badge text-bg-secondary">{{ $page->visibility->label() }}</span></td>
                            <td class="d-none d-md-table-cell text-end">{{ number_format($page->views) }}</td>
                            <td class="d-none d-lg-table-cell">{{ $page->author?->name ?? '—' }}</td>
                            <td class="d-none d-lg-table-cell small text-body-secondary">
                                {{ ($trash ? $page->deleted_at : $page->updated_at)?->diffForHumans() }}
                            </td>
                            <td class="text-end">
                                @unless ($trash)
                                    @can('update', $page)
                                        <a href="{{ route('pages.edit', $page) }}" hx-boost="false"
                                           class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Edit :title', ['title' => $page->title]) }}">
                                            <i class="fa-solid fa-pen"></i>
                                        </a>
                                    @endcan
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-body-secondary py-4">
                                {{ $trash ? __('The trash is empty.') : __('No pages match.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    {{ $pages->links() }}
</div>

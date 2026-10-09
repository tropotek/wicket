@php
    use App\Enums\MenuItemType;
    $kids = $children[$item->id] ?? collect();
@endphp
<li class="menu-row" data-id="{{ $item->id }}" data-type="{{ $item->type->value }}">
    <div class="d-flex flex-wrap align-items-center gap-2 border rounded px-2 py-1 mb-1 bg-body">
        <span class="menu-handle text-body-secondary px-1" title="{{ __('Drag') }}" role="button">
            <i class="fa-solid fa-grip-vertical"></i>
        </span>
        <i @class([
            'fa-solid fa-fw text-body-secondary',
            'fa-link' => $item->type === MenuItemType::Item,
            'fa-caret-down' => $item->type === MenuItemType::Dropdown,
            'fa-minus' => $item->type === MenuItemType::Divider,
        ])></i>

        @if ($item->type === MenuItemType::Divider)
            <span class="text-body-secondary small me-auto">{{ __('Divider') }}</span>
        @else
            <input name="name" value="{{ $item->name }}" maxlength="255" required
                   class="form-control form-control-sm w-auto flex-grow-1" style="max-width: 20rem"
                   aria-label="{{ __('Name') }}"
                   hx-patch="{{ route('admin.menu.update', $item) }}" hx-trigger="change"
                   hx-target="#menu-editor" hx-swap="outerHTML">
            <span class="me-auto small">
                @if ($item->page)
                    <a href="{{ url($item->page->slug) }}" class="text-body-secondary">/{{ $item->page->slug }}</a>
                @endif
            </span>
        @endif

        @if ($item->type === MenuItemType::Item)
            @if (! $item->page)
                <span class="badge text-bg-danger">{{ __('No page') }}</span>
            @elseif ($item->page->trashed())
                <span class="badge text-bg-danger">{{ __('In trash') }}</span>
            @elseif (! $item->page->publish)
                <span class="badge text-bg-warning">{{ __('Unpublished') }}</span>
            @endif
        @endif
        @if ($item->type === MenuItemType::Divider && $item->parent_id === null)
            <span class="badge text-bg-secondary">{{ __('Hidden in navbar') }}</span>
        @endif

        <button type="button" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}"
                hx-delete="{{ route('admin.menu.destroy', $item) }}"
                hx-target="#menu-editor" hx-swap="outerHTML"
                hx-confirm="{{ $kids->isNotEmpty()
                    ? trans_choice('Delete ":name" and its :count item?|Delete ":name" and its :count items?', $kids->count(), ['name' => $item->name])
                    : ($item->type === MenuItemType::Divider ? __('Delete this divider?') : __('Delete ":name"?', ['name' => $item->name])) }}">
            <i class="fa-solid fa-trash"></i>
        </button>
    </div>

    @if ($item->type === MenuItemType::Dropdown)
        <ul class="menu-list menu-children list-unstyled ms-4 mb-2" data-menu-list data-nested>
            @foreach ($kids as $child)
                @include('admin.menu._row', ['item' => $child])
            @endforeach
        </ul>
    @endif
</li>

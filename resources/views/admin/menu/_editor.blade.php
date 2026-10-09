{{-- Swapped whole by every HTMX write. $input holds the add form's values after an HTMX validation error. --}}
@php($input = ($input ?? []) + ['type' => old('type', 'item'), 'page_id' => old('page_id'), 'name' => old('name')])
<div id="menu-editor">
    @if ($status ?? null)
        <div class="alert alert-success alert-dismissible fade show py-2">
            {{ $status }}
            <button type="button" class="btn-close py-2" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.menu.store') }}" class="row g-2 align-items-end mb-4"
          hx-post="{{ route('admin.menu.store') }}" hx-target="#menu-editor" hx-swap="outerHTML"
          x-data="{ type: @js($input['type']) }">
        @csrf
        <div class="col-sm-3">
            <label for="menu-type" class="form-label small">{{ __('Add') }}</label>
            <select id="menu-type" name="type" class="form-select" x-model="type">
                <option value="item">{{ __('Page link') }}</option>
                <option value="dropdown">{{ __('Dropdown') }}</option>
                <option value="divider">{{ __('Divider') }}</option>
            </select>
        </div>
        <div class="col-sm-5" x-show="type === 'item'">
            <label for="menu-page" class="form-label small">{{ __('Page') }}</label>
            <select id="menu-page" name="page_id" class="form-select" :disabled="type !== 'item'">
                <option value="">{{ __('Choose a page…') }}</option>
                @foreach ($pages as $page)
                    <option value="{{ $page->id }}" @selected((string) $input['page_id'] === (string) $page->id)>{{ $page->title }} ({{ $page->slug }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm" x-show="type !== 'divider'">
            <label for="menu-name" class="form-label small">{{ __('Name') }}</label>
            <input id="menu-name" name="name" value="{{ $input['name'] }}" class="form-control" maxlength="255" :disabled="type === 'divider'"
                   :placeholder="type === 'item' ? @js(__('Page title')) : ''">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>{{ __('Add') }}</button>
        </div>
    </form>

    @if ($items->isEmpty())
        <p class="text-body-secondary">{{ __('The menu is empty.') }}</p>
    @endif
    <ul class="menu-list list-unstyled mb-0" data-menu-list data-menu-root
        data-order-url="{{ route('admin.menu.order') }}">
        @foreach ($items as $item)
            @include('admin.menu._row', ['item' => $item])
        @endforeach
    </ul>
</div>

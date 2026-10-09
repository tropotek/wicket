{{-- Swapped whole by navigation and every write. Children inherit the target/swap and the picker value. --}}
@php
    use Illuminate\Support\Number;
    $link = fn (string $path) => route('media.index', array_filter(['dir' => $path, 'picker' => $picker]));
    $icon = fn (string $name) => match (strtolower(pathinfo($name, PATHINFO_EXTENSION))) {
        'pdf' => 'fa-file-pdf',
        'doc', 'docx', 'odt' => 'fa-file-word',
        'xls', 'xlsx', 'ods', 'csv' => 'fa-file-excel',
        'ppt', 'pptx', 'odp' => 'fa-file-powerpoint',
        'zip' => 'fa-file-zipper',
        'txt' => 'fa-file-lines',
        default => 'fa-file',
    };
@endphp
<div id="media-browser" hx-target="#media-browser" hx-swap="outerHTML"
     @if ($picker) hx-vals='{"picker": "{{ $picker }}"}' @endif>

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

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <nav aria-label="{{ __('Folder') }}" class="me-auto">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="{{ $link('') }}" hx-get="{{ $link('') }}" hx-push-url="true"><i class="fa-solid fa-house"></i> {{ __('media') }}</a>
                </li>
                @foreach ($crumbs as $crumb)
                    @if ($loop->last)
                        <li class="breadcrumb-item active" aria-current="page">{{ $crumb['name'] }}</li>
                    @else
                        <li class="breadcrumb-item">
                            <a href="{{ $link($crumb['path']) }}" hx-get="{{ $link($crumb['path']) }}" hx-push-url="true">{{ $crumb['name'] }}</a>
                        </li>
                    @endif
                @endforeach
            </ol>
        </nav>

        <form method="POST" action="{{ route('media.folders') }}" class="d-flex gap-1"
              hx-post="{{ route('media.folders') }}" x-data="{ open: false }">
            @csrf
            <input type="hidden" name="dir" value="{{ $dir }}">
            <input name="name" class="form-control form-control-sm" placeholder="{{ __('Folder name') }}"
                   maxlength="100" x-show="open" x-cloak x-ref="name" @keydown.escape="open = false" aria-label="{{ __('Folder name') }}">
            <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" x-show="! open"
                    @click="open = true; $nextTick(() => $refs.name.focus())">
                <i class="fa-solid fa-folder-plus me-1"></i>{{ __('New folder') }}
            </button>
            <button type="submit" class="btn btn-sm btn-primary" x-show="open" x-cloak>{{ __('Create') }}</button>
        </form>
    </div>

    <form method="POST" action="{{ route('media.files') }}" enctype="multipart/form-data"
          hx-post="{{ route('media.files') }}" hx-encoding="multipart/form-data" hx-trigger="change"
          class="border border-2 rounded p-3 mb-3 text-center text-body-secondary"
          :class="dragging && 'border-primary bg-primary-subtle'"
          x-data="{ dragging: false }"
          @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
          @drop.prevent="dragging = false; $refs.files.files = $event.dataTransfer.files; $refs.files.dispatchEvent(new Event('change', { bubbles: true }))">
        @csrf
        <input type="hidden" name="dir" value="{{ $dir }}">
        <i class="fa-solid fa-cloud-arrow-up me-1"></i>{{ __('Drop files here or') }}
        <label class="btn btn-sm btn-outline-primary ms-1 mb-0">
            {{ __('choose files') }}
            <input type="file" name="files[]" multiple class="d-none" x-ref="files"
                   accept="{{ collect($extensions)->map(fn ($e) => '.'.$e)->implode(',') }}">
        </label>
        <noscript><button type="submit" class="btn btn-sm btn-primary ms-1">{{ __('Upload') }}</button></noscript>
        <div class="small mt-1">{{ __('Allowed: :list. Max :size each.', ['list' => implode(', ', $extensions), 'size' => Number::fileSize(config('wicket.upload_max_kb') * 1024)]) }}</div>
    </form>

    @if ($entries->isEmpty())
        <p class="text-body-secondary">{{ __('This folder is empty.') }}</p>
    @endif

    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-xl-6 g-2">
        @foreach ($entries as $e)
            <div class="col">
                <div class="card h-100" x-data="{ renaming: false }">
                    @if ($e['type'] === 'dir')
                        <a href="{{ $link($e['path']) }}" hx-get="{{ $link($e['path']) }}" hx-push-url="true"
                           class="d-flex align-items-center justify-content-center text-warning bg-body-tertiary rounded-top"
                           style="height: 110px" aria-label="{{ __('Open :name', ['name' => $e['name']]) }}">
                            <i class="fa-solid fa-folder fa-3x"></i>
                        </a>
                    @elseif ($e['image'])
                        <a href="{{ $e['url'] }}" target="_blank" class="d-block bg-body-tertiary rounded-top text-center" style="height: 110px">
                            <img src="{{ route('media.thumb', ['path' => $e['path']]) }}" alt="" loading="lazy"
                                 class="mw-100 mh-100 object-fit-contain" style="height: 110px">
                        </a>
                    @else
                        <a href="{{ $e['url'] }}" target="_blank"
                           class="d-flex align-items-center justify-content-center text-body-secondary bg-body-tertiary rounded-top"
                           style="height: 110px">
                            <i class="fa-solid {{ $icon($e['name']) }} fa-3x"></i>
                        </a>
                    @endif

                    <div class="card-body p-2 small">
                        <div class="text-truncate fw-medium" title="{{ $e['name'] }}" x-show="! renaming">{{ $e['name'] }}</div>
                        <form x-show="renaming" x-cloak hx-patch="{{ route('media.rename') }}">
                            <div class="d-flex gap-1">
                            <input type="hidden" name="path" value="{{ $e['path'] }}">
                            <input name="name" value="{{ $e['type'] === 'file' ? pathinfo($e['name'], PATHINFO_FILENAME) : $e['name'] }}"
                                   class="form-control form-control-sm" maxlength="100" x-ref="rename"
                                   @keydown.escape="renaming = false" aria-label="{{ __('New name') }}">
                            <button type="submit" class="btn btn-sm btn-primary" aria-label="{{ __('Save') }}"><i class="fa-solid fa-check"></i></button>
                            </div>
                        </form>
                        <div class="text-body-secondary">
                            {{ $e['size'] !== null ? Number::fileSize($e['size'], 1).' · ' : '' }}{{ \Carbon\Carbon::createFromTimestamp($e['modified'])->toDateString() }}
                        </div>
                    </div>

                    <div class="card-footer p-1 d-flex gap-1 justify-content-end">
                        @if ($picker && $e['type'] === 'file' && ($picker === 'file' || $e['image']))
                            <button type="button" class="btn btn-sm btn-primary me-auto"
                                    @click="window.parent.postMessage({ mceAction: 'wicketMediaPick', url: @js($e['url']), name: @js($e['name']) }, window.location.origin)">
                                {{ __('Select') }}
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-secondary" aria-label="{{ __('Rename') }}" title="{{ __('Rename') }}"
                                @click="renaming = ! renaming; $nextTick(() => $refs.rename.select())">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}"
                                hx-delete="{{ route('media.destroy') }}" hx-vals='@json(['path' => $e['path']])'
                                hx-confirm="{{ $e['type'] === 'dir'
                                    ? trans_choice('Delete folder ":name" and the :count file in it?|Delete folder ":name" and the :count files in it?', $counts($e['path']), ['name' => $e['name']])
                                    : __('Delete ":name"?', ['name' => $e['name']]) }}">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

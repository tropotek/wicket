{{-- Rendered page HTML (sanitised on save, task 90; render passes applied) with the image lightbox. --}}
@if (preg_match('/\b(?:language|lang)-[\w-]+/', $html))
    @push('head')
        @vite('resources/js/prism.js')
    @endpush
@endif
<div class="wk-content" x-data="lightbox" @click="open($event)"
     @keydown.window.escape="close()" @keydown.window.arrow-right="step(1)" @keydown.window.arrow-left="step(-1)">
    {!! $html !!}

    <template x-if="current">
        <div class="wk-lightbox-overlay position-fixed top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center p-3"
             style="z-index: 1080; background: rgba(0, 0, 0, .9)" role="dialog" aria-modal="true" aria-label="{{ __('Image viewer') }}"
             @click.self="close()">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" x-ref="close"
                    @click="close()" aria-label="{{ __('Close') }}"></button>
            <template x-if="images.length > 1">
                <div>
                    <button type="button" class="btn btn-link link-light position-absolute top-50 start-0 translate-middle-y fs-2"
                            @click="step(-1)" aria-label="{{ __('Previous image') }}"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="btn btn-link link-light position-absolute top-50 end-0 translate-middle-y fs-2"
                            @click="step(1)" aria-label="{{ __('Next image') }}"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </template>
            <img :src="current.src" :alt="current.alt" class="mw-100 object-fit-contain" style="max-height: calc(100vh - 6rem)" @click.self="close()">
            <div class="text-light small mt-2" x-show="current.alt || images.length > 1">
                <span x-text="current.alt"></span>
                <span class="text-white-50 ms-2" x-show="images.length > 1" x-text="(index + 1) + ' / ' + images.length"></span>
            </div>
        </div>
    </template>
</div>

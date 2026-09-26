{{--
    Full-screen viewer for images, videos and PDFs (fmBrowser viewer* state).
    Teleported to <body>: sits above the picker modal (z-[9999]) and its key
    events never reach the grid shortcuts or the modal's Escape listener.
    fm.js finds it by id "fm-viewer-{livewire id}"; PDF pages are created by
    createPdfViewer() inside [data-fm-viewer-pages] (no Alpine children there).
--}}
<template x-teleport="body">
    <div
        class="fm-viewer"
        id="fm-viewer-{{ $this->getId() }}"
        data-fm-viewer
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('fm::labels.preview') }}"
        tabindex="-1"
        x-show="viewer.open"
        x-cloak
        x-on:keydown="onViewerKeydown($event)"
    >
        <header class="fm-viewer-head">
            <p
                class="fm-viewer-name"
                x-text="viewer.name"
            ></p>
            <div class="fm-viewer-tools">
                <span
                    class="fm-viewer-count"
                    x-show="viewerHasPages"
                    x-text="viewerPageLabel"
                ></span>
                <div
                    class="fm-viewer-zoom"
                    x-show="viewerIsPdf"
                >
                    <button
                        class="fm-viewer-btn"
                        type="button"
                        aria-label="{{ __('fm::labels.zoom_out') }}"
                        title="{{ __('fm::labels.zoom_out') }} (−)"
                        x-on:click="viewerZoomOut()"
                    ><i
                            class="ph ph-minus"
                            aria-hidden="true"
                        ></i></button>
                    <span
                        class="fm-viewer-zoom-value"
                        x-text="viewerZoomLabel"
                    ></span>
                    <button
                        class="fm-viewer-btn"
                        type="button"
                        aria-label="{{ __('fm::labels.zoom_in') }}"
                        title="{{ __('fm::labels.zoom_in') }} (+)"
                        x-on:click="viewerZoomIn()"
                    ><i
                            class="ph ph-plus"
                            aria-hidden="true"
                        ></i></button>
                </div>
                <a
                    class="fm-viewer-btn"
                    x-bind:href="viewer.url"
                    x-bind:download="viewer.name"
                    aria-label="{{ __('fm::labels.download') }}"
                    title="{{ __('fm::labels.download') }}"
                ><i
                        class="ph ph-download-simple"
                        aria-hidden="true"
                    ></i></a>
                <button
                    class="fm-viewer-btn"
                    type="button"
                    aria-label="{{ __('fm::labels.close') }}"
                    title="{{ __('fm::labels.close') }} (Esc)"
                    x-on:click="closeViewer()"
                ><i
                        class="ph ph-x"
                        aria-hidden="true"
                    ></i></button>
            </div>
        </header>

        <div class="fm-viewer-stage">
            <button
                class="fm-viewer-nav is-prev"
                type="button"
                x-show="viewerHasPrev"
                aria-label="{{ __('fm::labels.previous') }}"
                title="{{ __('fm::labels.previous') }} (←)"
                x-on:click="viewerPrev()"
            ><i
                    class="ph ph-caret-left"
                    aria-hidden="true"
                ></i></button>

            {{-- x-if (not x-show): switching files / closing drops the element, which stops playback and downloads. --}}
            <template x-if="viewerIsImage">
                <img
                    class="fm-viewer-img"
                    x-bind:src="viewer.url"
                    x-bind:alt="viewer.name"
                    x-on:error="viewerFailed()"
                >
            </template>
            <template x-if="viewerIsVideo">
                <video
                    class="fm-viewer-video"
                    x-bind:src="viewer.url"
                    controls
                    preload="metadata"
                    x-on:error="viewerFailed()"
                ></video>
            </template>
            <div
                class="fm-viewer-pages"
                data-fm-viewer-pages
                x-show="viewerIsPdf"
            ></div>

            <div
                class="fm-viewer-state"
                x-show="viewer.loading"
            ><i
                    class="ph ph-spinner fm-spin"
                    aria-hidden="true"
                ></i></div>
            <div
                class="fm-viewer-state"
                x-show="viewer.error"
            >
                <p>{{ __('fm::labels.preview_unavailable') }}</p>
                <a
                    class="fm-viewer-btn is-text"
                    x-bind:href="viewer.url"
                    x-bind:download="viewer.name"
                ><i
                        class="ph ph-download-simple"
                        aria-hidden="true"
                    ></i>{{ __('fm::labels.download') }}</a>
            </div>

            <button
                class="fm-viewer-nav is-next"
                type="button"
                x-show="viewerHasNext"
                aria-label="{{ __('fm::labels.next') }}"
                title="{{ __('fm::labels.next') }} (→)"
                x-on:click="viewerNext()"
            ><i
                    class="ph ph-caret-right"
                    aria-hidden="true"
                ></i></button>
        </div>
    </div>
</template>

<div x-data="{ pageName: {{ json_encode($this->title()) }} }">
    @assets
        <link
            rel="stylesheet"
            href="{{ \VmEngine\Fm\Http\Controllers\AssetController::url('fm.css') }}"
        >
        <script src="{{ \VmEngine\Fm\Http\Controllers\AssetController::url('fm.js') }}"></script>
    @endassets

    @include('synapps::components.layouts.partials.breadcrumbs', ['breadcrumbs' => $this->breadcrumbs])

    <x-synapse-confirm-dialog />

    @php
        $can = $this->permissions;
        $items = $this->items;
        $rootName = $this->currentFolderConfig['name'] ?? $currentFolder;
        $uploadInputId = 'fm-upload-' . $this->getId();
    @endphp

    @if ($this->currentFolderConfig === null)
        @include('fm::partials.not-configured', ['class' => 'fm'])
    @else
    <div
        class="fm"
        tabindex="-1"
        x-data="fmBrowser({{ json_encode($this->jsConfig) }})"
        x-on:keydown="onKeydown($event)"
    >
        @include('fm::partials.toolbar', [
            'mode' => 'manager',
            'folders' => $this->folders,
            'currentFolder' => $currentFolder,
            'rootName' => $rootName,
            'segments' => $this->pathSegments,
            'locked' => false,
            'showTrash' => $showTrash,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'viewMode' => $viewMode,
            'can' => $can,
            'clipboardCount' => count($clipboard['files']),
            'uploadInputId' => $uploadInputId,
        ])

        <div class="fm-body">
            @include('fm::partials.tree', [
                'tree' => $this->folderTree,
                'rootName' => $rootName,
                'subPath' => $subPath,
                'showTrash' => $showTrash,
                'withTrash' => true,
            ])

            <div
                class="fm-main"
                x-on:dragenter.prevent="onDragEnter()"
                x-on:dragleave.prevent="onDragLeave()"
                x-on:dragover.prevent
                x-on:drop.prevent="onDrop($event)"
            >
                <div
                    class="fm-progress"
                    x-show="uploading"
                    x-cloak
                >
                    <span
                        class="fm-progress-bar"
                        :style="progressStyle"
                    ></span>
                </div>

                @if ($can['upload'] && !$showTrash)
                    <div
                        class="fm-drop"
                        x-show="dragging"
                        x-cloak
                    >
                        <i
                            class="ph ph-cloud-arrow-up fm-drop-icon"
                            aria-hidden="true"
                        ></i>
                        <p class="fm-drop-text">{{ __('fm::labels.drop_to_upload') }}</p>
                    </div>
                @endif

                <div
                    class="fm-content"
                    wire:key="fm-list-{{ $listKey }}"
                    x-on:click="onBlankClick($event)"
                    x-on:contextmenu.prevent="openBlankMenu($event)"
                    x-on:scroll="closeMenu()"
                >
                    @if ($this->fileList['truncated'])
                        <p class="fm-search-note" role="status">{{ __('fm::labels.search_truncated', ['count' => \VmEngine\Fm\Services\FileManagerService::SEARCH_LIMIT]) }}</p>
                    @endif
                    @if ($items === [])
                        @include('fm::partials.empty', [
                            'search' => $search,
                            'showTrash' => $showTrash,
                            'can' => $can,
                            'uploadInputId' => $uploadInputId,
                            'mode' => 'manager',
                        ])
                    @elseif ($viewMode === 'list')
                        <div class="fm-table-wrap">
                            <table
                                class="fm-table"
                                role="grid"
                                aria-label="{{ __('fm::labels.files') }}"
                            >
                                <thead>
                                    <tr>
                                        <th class="fm-col-check"><span
                                                class="sr-only">{{ __('fm::labels.selection') }}</span></th>
                                        @foreach (['filename' => 'name', 'extension' => 'type', 'size' => 'size', 'created_at' => 'date'] as $column => $labelKey)
                                            <th
                                                class="{{ $column === 'extension' ? 'fm-col-type' : '' }}{{ $column === 'created_at' ? 'fm-col-date' : '' }}"
                                                aria-sort="{{ $sortBy === $column ? ($sortDir === 'asc' ? 'ascending' : 'descending') : 'none' }}"
                                            >
                                                <button
                                                    class="fm-sort"
                                                    type="button"
                                                    wire:click="applySort('{{ $column }}')"
                                                >
                                                    {{ __('fm::labels.' . $labelKey) }}
                                                    @if ($sortBy === $column)
                                                        <i
                                                            class="ph {{ $sortDir === 'asc' ? 'ph-caret-up' : 'ph-caret-down' }}"
                                                            aria-hidden="true"
                                                        ></i>
                                                    @endif
                                                </button>
                                            </th>
                                        @endforeach
                                        <th class="fm-col-more"><span
                                                class="sr-only">{{ __('fm::labels.actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($items as $item)
                                        @include('fm::partials.item-row', [
                                            'item' => $item,
                                            'selected' => $this->isSelected($item),
                                            'cut' => $this->isCut($item),
                                            'showLocation' => $showTrash || $search !== '',
                                        ])
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div
                            class="fm-grid"
                            role="listbox"
                            aria-multiselectable="true"
                            aria-label="{{ __('fm::labels.files') }}"
                        >
                            @foreach ($items as $item)
                                @include('fm::partials.item-tile', [
                                    'item' => $item,
                                    'mode' => 'manager',
                                    'selected' => $this->isSelected($item),
                                    'cut' => $this->isCut($item),
                                    'showLocation' => $showTrash || $search !== '',
                                ])
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if ($selected !== [] || $selectedDirs !== [])
            @include('fm::partials.pill', [
                'summary' => $this->selectionSummary,
                'showTrash' => $showTrash,
                'can' => $can,
            ])
        @endif

        @include('fm::partials.context-menu', ['showTrash' => $showTrash, 'can' => $can])
        @include('fm::partials.details-drawer', ['file' => $this->detailsFile, 'can' => $can])

        <input
            class="fm-hidden-input"
            id="{{ $uploadInputId }}"
            type="file"
            multiple
            x-ref="uploadInput"
            x-on:change="onFileInput($event)"
        >
    </div>
    @endif

    {{-- Image Preview --}}
    <x-synapse-lightbox name="fm-preview" />

    {{-- Rename Modal --}}
    <x-synapse-modal
        name="fm-rename"
        maxWidth="md"
    >
        <div class="syn-modal-header">
            <h3 class="syn-modal-title">{{ __('fm::labels.rename_file') }}</h3>
            <button
                class="syn-modal-close"
                type="button"
                aria-label="{{ __('fm::labels.close') }}"
                @click="$dispatch('close-modal-fm-rename')"
            >
                <i
                    class="ph ph-x"
                    aria-hidden="true"
                ></i>
            </button>
        </div>
        <div class="syn-modal-body">
            <div class="form-box required">
                <label for="fm-rename-input">{{ __('fm::labels.new_name') }}</label>
                <input
                    id="fm-rename-input"
                    class="form-input @error('renameName') has-error @enderror"
                    type="text"
                    wire:model="renameName"
                    @keydown.enter="$wire.confirmRename()"
                    x-init="$el.focus()"
                >
                @error('renameName')
                    <p class="form-error-message">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <div class="syn-modal-footer">
            <button
                class="btn secondary"
                type="button"
                @click="$dispatch('close-modal-fm-rename')"
            >{{ __('fm::labels.cancel') }}</button>
            <button
                class="btn primary"
                type="button"
                wire:click="confirmRename"
            >
                <i
                    class="ph ph-floppy-disk"
                    aria-hidden="true"
                ></i>{{ __('fm::labels.save') }}
            </button>
        </div>
    </x-synapse-modal>

    {{-- New Folder Modal --}}
    <x-synapse-modal
        name="fm-new-folder"
        maxWidth="md"
    >
        <div class="syn-modal-header">
            <h3 class="syn-modal-title">{{ __('fm::labels.new_folder') }}</h3>
            <button
                class="syn-modal-close"
                type="button"
                aria-label="{{ __('fm::labels.close') }}"
                @click="$dispatch('close-modal-fm-new-folder')"
            >
                <i
                    class="ph ph-x"
                    aria-hidden="true"
                ></i>
            </button>
        </div>
        <div class="syn-modal-body">
            <div class="form-box required">
                <label for="fm-new-folder-input">{{ __('fm::labels.folder_name') }}</label>
                <input
                    id="fm-new-folder-input"
                    class="form-input @error('newFolderName') has-error @enderror"
                    type="text"
                    wire:model="newFolderName"
                    @keydown.enter="$wire.createFolder()"
                    placeholder="{{ __('fm::labels.folder_name_placeholder') }}"
                    x-init="$el.focus()"
                >
                @error('newFolderName')
                    <p class="form-error-message">{{ $message }}</p>
                @enderror
                <p class="form-help">{{ __('fm::labels.folder_name_help') }}</p>
            </div>
        </div>
        <div class="syn-modal-footer">
            <button
                class="btn secondary"
                type="button"
                @click="$dispatch('close-modal-fm-new-folder')"
            >{{ __('fm::labels.cancel') }}</button>
            <button
                class="btn primary"
                type="button"
                wire:click="createFolder"
            >
                <i
                    class="ph ph-folder-plus"
                    aria-hidden="true"
                ></i>{{ __('fm::labels.create') }}
            </button>
        </div>
    </x-synapse-modal>
</div>

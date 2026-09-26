<div x-data="fmBrowser({{ json_encode($this->jsConfig) }})">
    @assets
        <link rel="stylesheet" href="{{ \VmEngine\Fm\Http\Controllers\AssetController::url('fm.css') }}">
        <script src="{{ \VmEngine\Fm\Http\Controllers\AssetController::url('fm.js') }}"></script>
    @endassets

    {{-- Form Field --}}
    @if ($label)
        <label class="form-label">{{ $label }}</label>
    @endif

    <div class="input-group">
        <input class="form-input grow" type="text" value="{{ $value }}" readonly placeholder="{{ __('fm::labels.no_file_selected') }}">
        <button class="input-group-item right btn primary" type="button" x-on:click="openPickerModal()">
            <i class="ph ph-folder-open" aria-hidden="true"></i>{{ __('fm::labels.browse') }}
        </button>
        @if ($value)
            <button class="input-group-item right btn" type="button" wire:click="$set('value', '')" title="{{ __('fm::labels.clear') }}" aria-label="{{ __('fm::labels.clear') }}">
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        @endif
    </div>

    @if ($value && in_array(strtolower(pathinfo($value, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif'], true))
        <img class="fm-picker-preview" src="{{ $value }}" alt="{{ __('fm::labels.selected_file') }}">
    @endif

    {{-- Picker Modal --}}
    <x-synapse-modal name="{{ $this->modalName }}" maxWidth="6xl" class="relative z-[9999]">
        <div class="syn-modal-header">
            <h3 class="syn-modal-title">{{ __('fm::labels.select_file') }}</h3>
            <button class="syn-modal-close" type="button" aria-label="{{ __('fm::labels.close') }}" x-on:click="closePickerModal()">
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        </div>

        @if ($folderError)
            <div class="syn-empty-state">
                <i class="ph ph-warning syn-empty-state-icon" aria-hidden="true"></i>
                <p class="syn-empty-state-text">{{ __('fm::labels.invalid_folder') }}</p>
                <p class="syn-empty-state-text"><code>{{ $folder }}</code></p>
            </div>
        @elseif ($this->currentFolderConfig === null)
            @include('fm::partials.not-configured', ['class' => 'fm fm-in-picker'])
        @else
            @php
                $can = ['upload' => $this->canUpload, 'mkdir' => $this->canMkdir, 'rename' => false, 'move' => false, 'copy' => false, 'delete' => false];
                $items = $this->items;
                $rootName = $this->currentFolderConfig['name'] ?? $currentFolder;
                $uploadInputId = 'fm-upload-'.$this->getId();
                $picked = $this->pickedFile;
            @endphp

            <div class="fm fm-in-picker" x-on:fm-folder-created.window="closeDropdown()">
                @include('fm::partials.toolbar', [
                    'mode' => 'picker',
                    'folders' => $this->folders,
                    'currentFolder' => $currentFolder,
                    'rootName' => $rootName,
                    'segments' => $this->pathSegments,
                    'locked' => $this->isLocked,
                    'showTrash' => false,
                    'sortBy' => 'filename',
                    'sortDir' => 'asc',
                    'viewMode' => 'grid',
                    'can' => $can,
                    'clipboardCount' => 0,
                    'uploadInputId' => $uploadInputId,
                ])

                <div class="fm-body">
                    @include('fm::partials.tree', [
                        'tree' => $this->folderTree,
                        'rootName' => $rootName,
                        'subPath' => $subPath,
                        'showTrash' => false,
                        'withTrash' => false,
                    ])

                    <div
                        class="fm-main"
                        x-on:dragenter.prevent="onDragEnter()"
                        x-on:dragleave.prevent="onDragLeave()"
                        x-on:dragover.prevent
                        x-on:drop.prevent="onDrop($event)"
                    >
                        <div class="fm-progress" x-show="uploading" x-cloak>
                            <span class="fm-progress-bar" :style="progressStyle"></span>
                        </div>

                        @if ($can['upload'])
                            <div class="fm-drop" x-show="dragging" x-cloak>
                                <i class="ph ph-cloud-arrow-up fm-drop-icon" aria-hidden="true"></i>
                                <p class="fm-drop-text">{{ __('fm::labels.drop_to_upload') }}</p>
                            </div>
                        @endif

                        <div class="fm-content">
                            @if ($this->fileList['truncated'])
                                <p class="fm-search-note" role="status">{{ __('fm::labels.search_truncated', ['count' => \VmEngine\Fm\Services\FileManagerService::SEARCH_LIMIT]) }}</p>
                            @endif
                            @if ($items === [])
                                @include('fm::partials.empty', [
                                    'search' => $search,
                                    'showTrash' => false,
                                    'can' => $can,
                                    'uploadInputId' => $uploadInputId,
                                    'mode' => 'picker',
                                ])
                            @else
                                <div class="fm-grid" role="listbox" aria-label="{{ __('fm::labels.files') }}">
                                    @foreach ($items as $item)
                                        @include('fm::partials.item-tile', [
                                            'item' => $item,
                                            'mode' => 'picker',
                                            'selected' => $this->isSelected($item),
                                            'cut' => false,
                                            'showLocation' => $search !== '',
                                        ])
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <footer class="fm-picker-footer">
                    <div class="fm-picker-info" aria-live="polite">
                        @if ($picked)
                            <strong title="{{ $picked->filename }}">{{ $picked->filename }}</strong>
                            <span>· {{ $picked->getHumanSize() }}</span>
                            @if ($picked->dimensions())
                                <span>· {{ $picked->dimensions() }}</span>
                            @endif
                        @else
                            <span>{{ __('fm::labels.click_to_select') }}</span>
                        @endif
                    </div>
                    <div class="fm-picker-actions">
                        <button class="fm-btn" type="button" x-on:click="closePickerModal()">{{ __('fm::labels.cancel') }}</button>
                        <button class="fm-btn is-primary" type="button" wire:click="choose" @disabled($picked === null)>
                            <i class="ph ph-check" aria-hidden="true"></i>{{ __('fm::labels.choose') }}
                        </button>
                    </div>
                </footer>

                <input
                    class="fm-hidden-input"
                    id="{{ $uploadInputId }}"
                    type="file"
                    multiple
                    @if ($accept !== '*') accept="{{ $accept }}" @endif
                    x-ref="uploadInput"
                    x-on:change="onFileInput($event)"
                >
            </div>
        @endif
    </x-synapse-modal>
</div>

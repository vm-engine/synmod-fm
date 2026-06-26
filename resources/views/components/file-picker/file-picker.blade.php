<div
    x-data="{
        pickerOpen: false,
        pickerKey: @js($pickerKey),
        selectedUrl: @entangle('value').live,
        dragging: false,
        dragCounter: 0,
        uploading: false,
        maxSizeKb: @js((int) ($this->uploadConfig['max_size_kb'] ?? 10240)),
        acceptAttr: @js($accept),
        fileMatchesAccept(file) {
            if (this.acceptAttr === '*') return true;
            return this.acceptAttr.split(',').map(p => p.trim()).some(pattern => {
                if (pattern.endsWith('/*')) return file.type.startsWith(pattern.slice(0, -2));
                if (pattern.startsWith('.')) return file.name.toLowerCase().endsWith(pattern.toLowerCase());
                return file.type === pattern;
            });
        },
        validateFiles(files) {
            const maxBytes = this.maxSizeKb * 1024;
            const maxLabel = this.maxSizeKb >= 1024 ?
                (this.maxSizeKb / 1024).toFixed(1).replace(/\.0$/, '') + ' MB' :
                this.maxSizeKb + ' KB';
            for (const file of files) {
                if (!this.fileMatchesAccept(file)) {
                    return '{{ __('fm::labels.upload_invalid_type') }}';
                }
                if (file.size > maxBytes) {
                    return '{{ __('fm::labels.upload_too_large') }}'.replace(':max', maxLabel);
                }
            }
            return null;
        },
        handleFiles(files) {
            if (!files.length) return;
            const error = this.validateFiles(files);
            if (error) {
                this.$dispatch('notify', { variant: 'danger', title: '{{ __('fm::labels.error') }}', message: error });
                return;
            }
            this.uploading = true;
            $wire.uploadMultiple('uploadFiles', files,
                () => { this.uploading = false; },
                () => {
                    this.uploading = false;
                    this.$dispatch('notify', { variant: 'danger', title: '{{ __('fm::labels.error') }}', message: '{{ __('fm::labels.upload_failed') }}' });
                },
                () => {}
            );
        },
        handleDrop(e) {
            this.dragCounter = 0;
            this.dragging = false;
            this.handleFiles(Array.from(e.dataTransfer.files));
        },
    }"
    x-init="const evtOpen = pickerKey ? 'fm-picker-open-' + pickerKey : 'fm-picker-open';
    window.addEventListener(evtOpen, () => { pickerOpen = true; });
    window.addEventListener('fm-picker-close', () => { pickerOpen = false; });
    window.addEventListener('fm:open-picker', (e) => {
        const detail = e.detail || {};
        if (detail.key && pickerKey && detail.key !== pickerKey) return;
        if (detail.accept) { acceptAttr = detail.accept; }
        pickerOpen = true;
    });"
    x-on:livewire-upload-error="$dispatch('notify', { variant: 'danger', title: '{{ __('fm::labels.error') }}', message: '{{ __('fm::labels.upload_failed') }}' })"
>
    {{-- Form Field --}}
    @if ($label)
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $label }}
        </label>
    @endif

    <div class="input-group">
        <input
            class="form-input grow"
            type="text"
            :value="selectedUrl"
            readonly
            placeholder="{{ __('fm::labels.no_file_selected') }}"
        >
        <button
            class="input-group-item right btn primary"
            type="button"
            @click="pickerOpen = true"
        >
            <span class="fa-solid fa-folder-open mr-1"></span>
            {{ __('fm::labels.browse') }}
        </button>
        @if ($value)
            <button
                class="input-group-item right btn"
                type="button"
                @click="selectedUrl = ''; $wire.set('value', '')"
                title="{{ __('fm::labels.clear') }}"
            >
                <span class="fa-solid fa-times"></span>
            </button>
        @endif
    </div>

    @if ($value)
        <div class="mt-2">
            @php
                $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
            @endphp
            @if ($isImage)
                <img
                    src="{{ $value }}"
                    alt="{{ __('fm::labels.selected_file') }}"
                    class="h-20 w-20 rounded-lg border border-gray-200 object-cover dark:border-gray-700"
                >
            @endif
        </div>
    @endif

    {{-- Picker Modal --}}
    <div
        class="fixed inset-0 z-[9999] flex items-start justify-center overflow-y-auto bg-black/60 py-8"
        x-show="pickerOpen"
        x-cloak
        x-transition
        @click.self="pickerOpen = false"
    >
        <div
            class="w-full max-w-4xl rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
            @click.stop
        >
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-gray-200 p-5 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <span class="fa-solid fa-folder-open mr-2 text-brand-600"></span>
                    {{ __('fm::labels.select_file') }}
                </h3>
                <button
                    type="button"
                    @click="pickerOpen = false"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <span class="fa-solid fa-times text-lg"></span>
                </button>
            </div>

            {{-- Error: invalid folder parameter --}}
            @if ($folderError)
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <span class="fa-solid fa-triangle-exclamation mb-3 text-4xl text-red-400"></span>
                    <p class="text-sm font-semibold text-red-600 dark:text-red-400">
                        {{ __('fm::labels.invalid_folder') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        <code class="rounded bg-gray-100 px-1 py-0.5 dark:bg-gray-800">{{ $folder }}</code>
                    </p>
                </div>
            @else
                {{-- Toolbar --}}
                <div class="flex flex-wrap items-center gap-3 border-b border-gray-100 p-4 dark:border-gray-800">
                    {{-- Folder Selector: hidden when locked to a specific folder --}}
                    @if (!$this->isLocked && count($this->folders) > 1)
                        <div class="min-w-[160px]">
                            <select
                                class="form-input py-2 text-sm"
                                wire:model.live="currentFolder"
                            >
                                @foreach ($this->folders as $folder)
                                    <option value="{{ $folder['path'] }}">{{ $folder['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Breadcrumb --}}
                    <div class="flex flex-1 items-center gap-1 text-sm">
                        <button
                            class="rounded px-2 py-1 text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-900/20"
                            type="button"
                            wire:click="navigateTo('')"
                        >
                            <span class="fa-solid fa-home text-xs"></span>
                            <span class="ml-1">{{ $this->currentFolderConfig['name'] ?? $currentFolder }}</span>
                        </button>
                        @foreach ($this->pathSegments as $segment)
                            <span class="text-gray-400">/</span>
                            <button
                                class="rounded px-2 py-1 text-brand-600 hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-900/20"
                                type="button"
                                wire:click="navigateTo('{{ $segment['path'] }}')"
                            >{{ $segment['name'] }}</button>
                        @endforeach
                    </div>

                    {{-- Upload --}}
                    @canAccess('fm.manage.create')
                    @if ($this->canUpload)
                        <label
                            class="btn primary cursor-pointer py-2 text-sm"
                            :class="{ 'opacity-60 pointer-events-none': uploading }"
                        >
                            <span
                                class="fa-solid fa-upload"
                                x-show="!uploading"
                            ></span>
                            <span
                                class="fa-solid fa-circle-notch fa-spin"
                                x-show="uploading"
                                x-cloak
                            ></span>
                            <span class="ml-1">{{ __('fm::labels.upload') }}</span>
                            <input
                                type="file"
                                class="hidden"
                                multiple
                                @if ($accept !== '*') accept="{{ $accept }}" @endif
                                @change="handleFiles(Array.from($event.target.files)); $event.target.value = ''"
                            >
                        </label>
                    @endif
                    @endcanAccess

                    {{-- Search --}}
                    <input
                        class="form-input w-44 py-2 text-sm"
                        type="text"
                        placeholder="{{ __('fm::labels.search_placeholder') }}"
                        wire:model.live.debounce="search"
                    >
                </div>

                {{-- File Browser --}}
                <div
                    class="relative p-4"
                    x-on:dragenter.prevent="dragCounter++; dragging = true"
                    x-on:dragleave.prevent="dragCounter--; if (dragCounter === 0) dragging = false"
                    x-on:dragover.prevent
                    x-on:drop.prevent="handleDrop($event)"
                >
                    {{-- Drop Overlay --}}
                    @canAccess('fm.manage.create')
                    @if ($this->canUpload)
                        <div
                            x-show="dragging"
                            x-cloak
                            class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-brand-400 bg-brand-50/90 dark:bg-brand-900/80"
                        >
                            <span class="fa-solid fa-cloud-arrow-up text-4xl text-brand-500"></span>
                            <p class="text-sm font-semibold text-brand-600 dark:text-brand-300">
                                {{ __('fm::labels.drop_to_upload') }}</p>
                        </div>
                    @endif
                    @endcanAccess
                    {{-- Directories --}}
                    @if (count($this->fileList['dirs']) > 0)
                        <div class="mb-4">
                            <p
                                class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ __('fm::labels.folders') }}
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($this->fileList['dirs'] as $dir)
                                    <button
                                        class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-medium text-gray-700 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300"
                                        type="button"
                                        wire:click="navigateTo('{{ $subPath ? $subPath . '/' . $dir : $dir }}')"
                                        wire:key="picker-dir-{{ $dir }}"
                                    >
                                        <span class="fa-solid fa-folder text-yellow-500 dark:text-yellow-400"></span>
                                        {{ $dir }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Files --}}
                    @if (count($this->fileList['files']) === 0 && count($this->fileList['dirs']) === 0)
                        <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-600">
                            <span class="fa-solid fa-folder-open mb-2 text-3xl"></span>
                            <p class="text-sm">{{ __('fm::labels.folder_empty') }}</p>
                        </div>
                    @else
                        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6">
                            @foreach ($this->fileList['files'] as $file)
                                <button
                                    class="group flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-gray-50 text-left transition hover:border-brand-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-800/50"
                                    type="button"
                                    wire:click="selectFile({{ $file->id }})"
                                    wire:key="picker-file-{{ $file->id }}"
                                    title="{{ $file->filename }}"
                                >
                                    <div class="flex h-20 items-center justify-center bg-gray-100 dark:bg-gray-900/40">
                                        @if ($file->isImage() && $file->has_thumbnail)
                                            <img
                                                src="{{ $file->getThumbnailUrl() }}"
                                                alt="{{ $file->filename }}"
                                                class="h-full w-full object-cover"
                                                loading="lazy"
                                            >
                                        @elseif ($file->isImage())
                                            <img
                                                src="{{ $file->getUrl() }}"
                                                alt="{{ $file->filename }}"
                                                class="h-full w-full object-cover"
                                                loading="lazy"
                                            >
                                        @elseif ($file->isVideo())
                                            <span
                                                class="fa-solid fa-circle-play text-3xl text-purple-400 dark:text-purple-500"
                                            ></span>
                                        @else
                                            @php
                                                $iconClass = match (strtolower($file->extension)) {
                                                    'pdf' => 'fa-solid fa-file-pdf text-red-500',
                                                    'doc', 'docx' => 'fa-solid fa-file-word text-blue-500',
                                                    'xls', 'xlsx' => 'fa-solid fa-file-excel text-green-600',
                                                    'zip', 'rar' => 'fa-solid fa-file-zipper text-yellow-600',
                                                    default => 'fa-solid fa-file text-gray-400',
                                                };
                                            @endphp
                                            <span class="text-3xl {{ $iconClass }}"></span>
                                        @endif
                                    </div>
                                    <div class="p-1.5">
                                        <p class="truncate text-[11px] font-medium text-gray-700 dark:text-gray-300">
                                            {{ $file->filename }}</p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between border-t border-gray-200 p-4 dark:border-gray-700">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('fm::labels.click_to_select') }}
                    </p>
                    <button
                        class="btn"
                        type="button"
                        @click="pickerOpen = false"
                    >
                        {{ __('fm::labels.cancel') }}
                    </button>
                </div>

            @endif {{-- end @else (no folderError) --}}
        </div>
    </div>
</div>

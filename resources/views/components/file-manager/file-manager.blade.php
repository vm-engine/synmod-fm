<div
    x-data="fmFileManager('{{ $this->title() }}')"
    x-on:fm-open-rename.window="renameOpen = true"
    x-on:fm-close-rename.window="renameOpen = false"
    x-on:fm-open-new-folder.window="newFolderOpen = true"
    x-on:fm-close-new-folder.window="newFolderOpen = false"
    x-on:fm-open-move.window="moveOpen = true"
    x-on:fm-close-move.window="moveOpen = false"
>
    @include('synapps::components.layouts.partials.breadcrumbs', ['breadcrumbs' => $this->breadcrumbs])

    <x-synapse-confirm-dialog />

    <div class="space-y-4 py-2">

        {{-- Toolbar --}}
        <div class="dark:bg-white/3 space-y-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800">

            {{-- Row 1: Search + Actions --}}
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                <div class="w-full sm:w-48 sm:shrink-0">
                    <input
                        class="form-input w-full"
                        type="text"
                        placeholder="{{ __('fm::labels.search_placeholder') }}"
                        wire:model.live.debounce="search"
                    >
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @canAccess('fm.manage.create')
                    <label
                        class="btn primary cursor-pointer text-sm"
                        wire:loading.attr="disabled"
                    >
                        <span class="fa-solid fa-upload"></span>
                        <span class="ml-1">{{ __('fm::labels.upload') }}</span>
                        <input
                            type="file"
                            class="hidden"
                            multiple
                            wire:model="uploadFiles"
                        >
                    </label>

                    <button
                        class="btn secondary text-sm whitespace-nowrap"
                        type="button"
                        @click="newFolderOpen = true"
                    >
                        <span class="fa-solid fa-folder-plus"></span>
                        <span class="ml-1 hidden sm:inline">{{ __('fm::labels.new_folder') }}</span>
                    </button>
                    @endcanAccess

                    {{-- Trash Toggle --}}
                    <button
                        class="btn text-sm whitespace-nowrap {{ $showTrash ? 'danger' : 'secondary' }}"
                        type="button"
                        wire:click="toggleTrash"
                        title="{{ $showTrash ? __('fm::labels.exit_trash') : __('fm::labels.view_trash') }}"
                    >
                        <span class="fa-solid fa-trash"></span>
                        <span
                            class="ml-1 hidden sm:inline">{{ $showTrash ? __('fm::labels.exit_trash') : __('fm::labels.trash') }}</span>
                    </button>
                </div>
            </div>

            {{-- Row 2: Folder Selector + Breadcrumb Path --}}
            <div class="flex flex-wrap items-center gap-3">

                {{-- Folder Selector --}}
                @if (count($this->folders) > 1)
                    <div class="w-full sm:w-auto sm:min-w-[180px]">
                        <select
                            class="form-input w-full py-2 text-sm"
                            wire:model.live="currentFolder"
                        >
                            @foreach ($this->folders as $folder)
                                <option value="{{ $folder['path'] }}">{{ $folder['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Breadcrumb Path --}}
                <div class="flex grow items-center gap-1 text-sm">
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

            </div>
        </div>

        {{-- Upload Progress --}}
        <div
            wire:loading
            wire:target="uploadFiles"
        >
            <x-synapse-alert
                type="info"
                message="{{ __('fm::labels.uploading') }}"
            />
        </div>

        {{-- Bulk Action Bar --}}
        @if (count($selected) > 0)
            <div
                class="dark:bg-white/3 flex flex-wrap items-center gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-3 dark:border-brand-800 dark:bg-brand-900/20">
                <span class="text-sm font-medium text-brand-700 dark:text-brand-300">
                    {{ count($selected) }} {{ __('fm::labels.items_selected') }}
                </span>
                <button
                    class="btn secondary text-sm"
                    type="button"
                    wire:click="clearSelection"
                >
                    <span class="fa-solid fa-times"></span>
                    {{ __('fm::labels.clear') }}
                </button>
                <button
                    class="btn secondary text-sm"
                    type="button"
                    wire:click="selectAll"
                >
                    <span class="fa-solid fa-check-double"></span>
                    {{ __('fm::labels.select_all') }}
                </button>

                @if (!$showTrash)
                    @if ($this->canDo('move'))
                        <button
                            class="btn secondary text-sm"
                            type="button"
                            @click="moveOpen = true"
                            wire:click="$set('moveAction', 'move')"
                        >
                            <span class="fa-solid fa-arrows-alt"></span>
                            {{ __('fm::labels.move') }}
                        </button>
                    @endif
                    @if ($this->canDo('copy'))
                        <button
                            class="btn secondary text-sm"
                            type="button"
                            @click="moveOpen = true"
                            wire:click="$set('moveAction', 'copy')"
                        >
                            <span class="fa-solid fa-copy"></span>
                            {{ __('fm::labels.copy') }}
                        </button>
                    @endif
                    @if ($this->canDo('delete'))
                        <button
                            class="btn btn-danger text-sm"
                            type="button"
                            data-title="{{ __('fm::labels.trash_selected_title') }}"
                            data-message="{{ __('fm::labels.trash_selected_message', ['count' => count($selected)]) }}"
                            data-confirm="{{ __('fm::labels.yes_trash') }}"
                            data-cancel="{{ __('fm::labels.cancel') }}"
                            data-component="{{ $this->getId() }}"
                            @click="$dispatch('confirm-dialog', {
                        title: $el.dataset.title,
                        message: $el.dataset.message,
                        confirmText: $el.dataset.confirm,
                        cancelText: $el.dataset.cancel,
                        confirmColor: 'danger',
                        icon: 'fa-solid fa-trash',
                        wireMethod: 'trashSelected',
                        wireParams: [],
                        wireComponent: $el.dataset.component
                    })"
                        >
                            <span class="fa-solid fa-trash"></span>
                            {{ __('fm::labels.trash') }}
                        </button>
                    @endif
                @else
                    <button
                        class="btn secondary text-sm"
                        type="button"
                        wire:click="restoreSelected"
                    >
                        <span class="fa-solid fa-undo"></span>
                        {{ __('fm::labels.restore') }}
                    </button>
                    @if ($this->canDo('delete'))
                        <button
                            class="btn btn-danger text-sm"
                            type="button"
                            data-title="{{ __('fm::labels.purge_selected_title') }}"
                            data-message="{{ __('fm::labels.purge_selected_message', ['count' => count($selected)]) }}"
                            data-confirm="{{ __('fm::labels.yes_purge') }}"
                            data-cancel="{{ __('fm::labels.cancel') }}"
                            data-component="{{ $this->getId() }}"
                            @click="$dispatch('confirm-dialog', {
                        title: $el.dataset.title,
                        message: $el.dataset.message,
                        confirmText: $el.dataset.confirm,
                        cancelText: $el.dataset.cancel,
                        confirmColor: 'danger',
                        icon: 'fa-solid fa-fire',
                        wireMethod: 'purgeSelected',
                        wireParams: [],
                        wireComponent: $el.dataset.component
                    })"
                        >
                            <span class="fa-solid fa-fire"></span>
                            {{ __('fm::labels.purge') }}
                        </button>
                    @endif
                @endif
            </div>
        @endif

        {{-- File Browser --}}
        <div
            wire:key="file-browser-{{ $this->listKey }}"
            class="dark:bg-white/3 relative rounded-2xl border border-gray-200 bg-white dark:border-gray-800"
            x-on:dragenter.prevent="handleDragEnter()"
            x-on:dragleave.prevent="handleDragLeave()"
            x-on:dragover.prevent
            x-on:drop.prevent="handleDrop($event)"
        >
            {{-- Drop Overlay --}}
            @canAccess('fm.manage.create')
            <div
                x-show="dragging"
                x-cloak
                class="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-brand-400 bg-brand-50/90 dark:bg-brand-900/80"
            >
                <span class="fa-solid fa-cloud-arrow-up text-4xl text-brand-500"></span>
                <p class="text-sm font-semibold text-brand-600 dark:text-brand-300">
                    {{ __('fm::labels.drop_to_upload') }}</p>
            </div>
            @endcanAccess

            {{-- File List Header: Sort + Refresh + View Toggle --}}
            <div
                class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-4 py-2 dark:border-gray-800">
                <div class="flex items-center gap-2">
                    {{-- Sort --}}
                    <select
                        class="form-input py-1.5 text-sm"
                        style="width: 8rem; flex-shrink: 0"
                        x-on:change="$wire.call('applySort', $event.target.value)"
                    >
                        @foreach (['filename' => __('fm::labels.sort_name'), 'size' => __('fm::labels.sort_size'), 'extension' => __('fm::labels.sort_type'), 'created_at' => __('fm::labels.sort_date')] as $col => $label)
                            <option
                                value="{{ $col }}"
                                {{ $sortBy === $col ? 'selected' : '' }}
                            >{{ $label }}</option>
                        @endforeach
                    </select>
                    <button
                        class="btn secondary text-sm"
                        type="button"
                        wire:click="applySort('{{ $sortBy }}')"
                        title="{{ $sortDir === 'asc' ? __('fm::labels.sort_asc') : __('fm::labels.sort_desc') }}"
                    >
                        <span
                            class="fa-solid {{ $sortDir === 'asc' ? 'fa-arrow-up-a-z' : 'fa-arrow-down-z-a' }}"></span>
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Refresh --}}
                    <button
                        class="btn secondary text-sm"
                        type="button"
                        wire:click="$refresh"
                        title="{{ __('fm::labels.refresh') }}"
                    >
                        <span class="fa-solid fa-rotate-right"></span>
                    </button>

                    {{-- View Toggle --}}
                    <div class="flex rounded-lg border border-gray-200 dark:border-gray-700">
                        <button
                            class="rounded-l-lg px-3 py-2 text-sm transition {{ $viewMode === 'grid' ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                            type="button"
                            wire:click="toggleView"
                            title="{{ __('fm::labels.grid_view') }}"
                        ><span class="fa-solid fa-grip"></span></button>
                        <button
                            class="rounded-r-lg px-3 py-2 text-sm transition {{ $viewMode === 'list' ? 'bg-brand-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800' }}"
                            type="button"
                            wire:click="toggleView"
                            title="{{ __('fm::labels.list_view') }}"
                        ><span class="fa-solid fa-list"></span></button>
                    </div>
                </div>
            </div>

            {{-- Directories --}}
            @if (count($this->fileList['dirs']) > 0 && !$showTrash)
                <div class="border-b border-gray-100 p-4 dark:border-gray-800">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('fm::labels.folders') }}
                    </p>
                    <div class="grid grid-cols-1 gap-3 sm:flex sm:flex-wrap">
                        @foreach ($this->fileList['dirs'] as $dir)
                            <button
                                class="flex items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-medium text-gray-700 transition hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-300 dark:hover:border-brand-600 dark:hover:bg-brand-900/20 dark:hover:text-brand-300"
                                type="button"
                                wire:click="navigateTo('{{ $subPath ? $subPath . '/' . $dir : $dir }}')"
                                wire:key="dir-{{ $dir }}"
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
                <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-600">
                    <span class="fa-solid fa-folder-open mb-3 text-4xl"></span>
                    <p class="text-sm">{{ $showTrash ? __('fm::labels.trash_empty') : __('fm::labels.folder_empty') }}
                    </p>
                </div>
            @elseif(count($this->fileList['files']) === 0 && $showTrash)
                <div class="flex flex-col items-center justify-center py-16 text-gray-400 dark:text-gray-600">
                    <span class="fa-solid fa-trash mb-3 text-4xl"></span>
                    <p class="text-sm">{{ __('fm::labels.trash_empty') }}</p>
                </div>
            @elseif(count($this->fileList['files']) > 0)
                @if ($viewMode === 'grid')
                    {{-- Grid View --}}
                    <div
                        class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        @foreach ($this->fileList['files'] as $file)
                            <div
                                class="group relative flex cursor-pointer flex-col overflow-hidden rounded-xl border transition {{ in_array($file->id, $selected) ? 'border-brand-500 bg-brand-50 dark:bg-brand-900/20' : 'border-gray-200 bg-gray-50 hover:border-gray-300 dark:border-gray-700 dark:bg-gray-800/50 dark:hover:border-gray-600' }}"
                                wire:key="file-{{ $file->id }}"
                                wire:click="select({{ $file->id }})"
                            >
                                {{-- Selection Indicator --}}
                                <div class="absolute left-2 top-2 z-10">
                                    <div
                                        class="flex h-5 w-5 items-center justify-center rounded-full border-2 {{ in_array($file->id, $selected) ? 'border-brand-500 bg-brand-500' : 'border-white/70 bg-black/20 opacity-0 group-hover:opacity-100' }}">
                                        @if (in_array($file->id, $selected))
                                            <span class="fa-solid fa-check text-[10px] text-white"></span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Thumbnail / Icon --}}
                                <div class="flex h-28 items-center justify-center bg-gray-100 dark:bg-gray-900/40">
                                    @if ($file->isImage() && $file->has_thumbnail)
                                        <img
                                            src="{{ $file->getThumbnailUrl() }}"
                                            alt="{{ $file->filename }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                    @elseif($file->isImage())
                                        <img
                                            src="{{ $file->getUrl() }}"
                                            alt="{{ $file->filename }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                    @elseif($file->isVideo())
                                        <span
                                            class="fa-solid fa-circle-play text-4xl text-purple-400 dark:text-purple-500"
                                        ></span>
                                    @else
                                        <span
                                            class="text-4xl text-gray-400 dark:text-gray-600 {{ $this->fileIcon($file->extension) }}"
                                        ></span>
                                    @endif
                                </div>

                                {{-- File Info --}}
                                <div class="p-2">
                                    <p
                                        class="truncate text-xs font-medium text-gray-700 dark:text-gray-300"
                                        title="{{ $file->filename }}"
                                    >
                                        {{ $file->filename }}
                                    </p>
                                    <p class="text-[11px] text-gray-400 dark:text-gray-600">
                                        {{ $file->getHumanSize() }}</p>
                                </div>

                                {{-- Actions Overlay --}}
                                <div
                                    class="absolute right-1 top-1 flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100 rounded-md bg-white/90 px-1 py-0.5 shadow dark:bg-gray-800/90"
                                    x-on:click.stop
                                >
                                    @if ($file->isImage())
                                        <button
                                            class="btn-icon inline-flex h-5 w-5 items-center justify-center text-xs"
                                            type="button"
                                            title="{{ __('fm::labels.preview') }}"
                                            @click="showPreview('{{ $file->getUrl() }}')"
                                        ><span class="fa-solid fa-eye"></span></button>
                                    @endif

                                    @if (!$showTrash)
                                        <a
                                            class="btn-icon inline-flex h-5 w-5 items-center justify-center text-xs"
                                            href="{{ $file->getUrl() }}"
                                            download="{{ $file->filename }}"
                                            title="{{ __('fm::labels.download') }}"
                                        ><span class="fa-solid fa-download"></span></a>

                                        @if ($this->canDo('rename'))
                                            <button
                                                class="btn-icon inline-flex h-5 w-5 items-center justify-center text-xs"
                                                type="button"
                                                title="{{ __('fm::labels.rename') }}"
                                                wire:click="startRename({{ $file->id }})"
                                            ><span class="fa-solid fa-pen"></span></button>
                                        @endif
                                        @if ($this->canDo('delete'))
                                            <button
                                                class="btn-icon danger inline-flex h-5 w-5 items-center justify-center text-xs"
                                                type="button"
                                                title="{{ __('fm::labels.trash') }}"
                                                data-title="{{ __('fm::labels.trash_title') }}"
                                                data-message="{{ __('fm::labels.trash_message', ['name' => $file->filename]) }}"
                                                data-confirm="{{ __('fm::labels.yes_trash') }}"
                                                data-cancel="{{ __('fm::labels.cancel') }}"
                                                data-id="{{ $file->id }}"
                                                data-component="{{ $this->getId() }}"
                                                @click="$dispatch('confirm-dialog', {
                                        title: $el.dataset.title,
                                        message: $el.dataset.message,
                                        confirmText: $el.dataset.confirm,
                                        cancelText: $el.dataset.cancel,
                                        confirmColor: 'danger',
                                        icon: 'fa-solid fa-trash',
                                        wireMethod: 'trash',
                                        wireParams: [parseInt($el.dataset.id)],
                                        wireComponent: $el.dataset.component
                                    })"
                                            ><span class="fa-solid fa-trash"></span></button>
                                        @endif
                                    @else
                                        <button
                                            class="btn-icon inline-flex h-5 w-5 items-center justify-center text-xs"
                                            type="button"
                                            title="{{ __('fm::labels.restore') }}"
                                            data-title="{{ __('fm::labels.restore_title') }}"
                                            data-message="{{ __('fm::labels.restore_message', ['name' => $file->filename]) }}"
                                            data-confirm="{{ __('fm::labels.yes_restore') }}"
                                            data-cancel="{{ __('fm::labels.cancel') }}"
                                            data-id="{{ $file->id }}"
                                            data-component="{{ $this->getId() }}"
                                            @click="$dispatch('confirm-dialog', {
                                        title: $el.dataset.title,
                                        message: $el.dataset.message,
                                        confirmText: $el.dataset.confirm,
                                        cancelText: $el.dataset.cancel,
                                        confirmColor: 'info',
                                        icon: 'fa-solid fa-undo',
                                        wireMethod: 'restore',
                                        wireParams: [parseInt($el.dataset.id)],
                                        wireComponent: $el.dataset.component
                                    })"
                                        ><span class="fa-solid fa-undo"></span></button>
                                        @if ($this->canDo('delete'))
                                            <button
                                                class="btn-icon danger inline-flex h-5 w-5 items-center justify-center text-xs"
                                                type="button"
                                                title="{{ __('fm::labels.purge') }}"
                                                data-title="{{ __('fm::labels.purge_title') }}"
                                                data-message="{{ __('fm::labels.purge_message', ['name' => $file->filename]) }}"
                                                data-confirm="{{ __('fm::labels.yes_purge') }}"
                                                data-cancel="{{ __('fm::labels.cancel') }}"
                                                data-id="{{ $file->id }}"
                                                data-component="{{ $this->getId() }}"
                                                @click="$dispatch('confirm-dialog', {
                                        title: $el.dataset.title,
                                        message: $el.dataset.message,
                                        confirmText: $el.dataset.confirm,
                                        cancelText: $el.dataset.cancel,
                                        confirmColor: 'danger',
                                        icon: 'fa-solid fa-fire',
                                        wireMethod: 'purge',
                                        wireParams: [parseInt($el.dataset.id)],
                                        wireComponent: $el.dataset.component
                                    })"
                                            ><span class="fa-solid fa-fire"></span></button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    {{-- List View --}}
                    <div class="overflow-x-auto">
                        <table class="datatable min-w-full">
                            <thead>
                                <tr>
                                    <th class="w-8">
                                        <input
                                            type="checkbox"
                                            class="rounded"
                                            @change="$event.target.checked ? $wire.selectAll() : $wire.clearSelection()"
                                            :checked="{{ count($selected) > 0 && count($selected) === count($this->fileList['files']) ? 'true' : 'false' }}"
                                        >
                                    </th>
                                    @php
                                        $sortIcon =
                                            '<span class="fa-solid ml-1 ' .
                                            ($sortDir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') .
                                            ' text-brand-500"></span>';
                                    @endphp
                                    <th>
                                        <button
                                            type="button"
                                            class="flex items-center gap-1 font-semibold hover:text-brand-600"
                                            wire:click="applySort('filename')"
                                        >
                                            {{ __('fm::labels.name') }}
                                            @if ($sortBy === 'filename')
                                                {!! $sortIcon !!}
                                            @endif
                                        </button>
                                    </th>
                                    <th>
                                        <button
                                            type="button"
                                            class="flex items-center gap-1 font-semibold hover:text-brand-600"
                                            wire:click="applySort('extension')"
                                        >
                                            {{ __('fm::labels.type') }}
                                            @if ($sortBy === 'extension')
                                                {!! $sortIcon !!}
                                            @endif
                                        </button>
                                    </th>
                                    <th>
                                        <button
                                            type="button"
                                            class="flex items-center gap-1 font-semibold hover:text-brand-600"
                                            wire:click="applySort('size')"
                                        >
                                            {{ __('fm::labels.size') }}
                                            @if ($sortBy === 'size')
                                                {!! $sortIcon !!}
                                            @endif
                                        </button>
                                    </th>
                                    <th>
                                        <button
                                            type="button"
                                            class="flex items-center gap-1 font-semibold hover:text-brand-600"
                                            wire:click="applySort('created_at')"
                                        >
                                            {{ __('fm::labels.date') }}
                                            @if ($sortBy === 'created_at')
                                                {!! $sortIcon !!}
                                            @endif
                                        </button>
                                    </th>
                                    <th>
                                        <p>{{ __('fm::labels.actions') }}</p>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->fileList['files'] as $file)
                                    <tr
                                        wire:key="file-row-{{ $file->id }}"
                                        class="{{ in_array($file->id, $selected) ? 'bg-brand-50 dark:bg-brand-900/10' : '' }}"
                                    >
                                        <td>
                                            <input
                                                type="checkbox"
                                                class="rounded"
                                                wire:click="select({{ $file->id }})"
                                                :checked="{{ in_array($file->id, $selected) ? 'true' : 'false' }}"
                                            >
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                @if ($file->isImage() && $file->has_thumbnail)
                                                    <img
                                                        src="{{ $file->getThumbnailUrl() }}"
                                                        alt="{{ $file->filename }}"
                                                        class="h-8 w-8 rounded object-cover"
                                                        loading="lazy"
                                                    >
                                                @elseif ($file->isImage())
                                                    <img
                                                        src="{{ $file->getUrl() }}"
                                                        alt="{{ $file->filename }}"
                                                        class="h-8 w-8 rounded object-cover"
                                                        loading="lazy"
                                                    >
                                                @elseif ($file->isVideo())
                                                    <span
                                                        class="w-8 text-center text-lg text-purple-400 fa-solid fa-circle-play"
                                                    ></span>
                                                @else
                                                    <span
                                                        class="w-8 text-center text-lg text-gray-400 {{ $this->fileIcon($file->extension) }}"
                                                    ></span>
                                                @endif
                                                <span
                                                    class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $file->filename }}</span>
                                            </div>
                                        </td>
                                        <td><span class="text-sm text-gray-500">{{ $file->extension }}</span></td>
                                        <td><span class="text-sm text-gray-500">{{ $file->getHumanSize() }}</span>
                                        </td>
                                        <td><span
                                                class="text-sm text-gray-500">{{ $file->created_at->format('d M Y') }}</span>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-1">
                                                @if ($file->isImage())
                                                    <button
                                                        class="btn-icon has-tooltip group text-xs"
                                                        type="button"
                                                        @click="showPreview('{{ $file->getUrl() }}')"
                                                    >
                                                        <span class="fa-solid fa-eye"></span>
                                                        <span class="tooltip">{{ __('fm::labels.preview') }}</span>
                                                    </button>
                                                @endif
                                                <a
                                                    class="btn-icon has-tooltip group text-xs"
                                                    href="{{ $file->getUrl() }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                >
                                                    <span class="fa-solid fa-external-link-alt"></span>
                                                    <span class="tooltip">{{ __('fm::labels.open') }}</span>
                                                </a>
                                                @if (!$showTrash)
                                                    <a
                                                        class="btn-icon has-tooltip group text-xs"
                                                        href="{{ $file->getUrl() }}"
                                                        download="{{ $file->filename }}"
                                                    >
                                                        <span class="fa-solid fa-download"></span>
                                                        <span class="tooltip">{{ __('fm::labels.download') }}</span>
                                                    </a>
                                                    @if ($this->canDo('rename'))
                                                        <button
                                                            class="btn-icon warning has-tooltip group text-xs"
                                                            type="button"
                                                            wire:click="startRename({{ $file->id }})"
                                                        >
                                                            <span class="fa-solid fa-pen"></span>
                                                            <span
                                                                class="tooltip">{{ __('fm::labels.rename') }}</span>
                                                        </button>
                                                    @endif
                                                    @if ($this->canDo('delete'))
                                                        <button
                                                            class="btn-icon danger has-tooltip group text-xs"
                                                            type="button"
                                                            data-title="{{ __('fm::labels.trash_title') }}"
                                                            data-message="{{ __('fm::labels.trash_message', ['name' => $file->filename]) }}"
                                                            data-confirm="{{ __('fm::labels.yes_trash') }}"
                                                            data-cancel="{{ __('fm::labels.cancel') }}"
                                                            data-id="{{ $file->id }}"
                                                            data-component="{{ $this->getId() }}"
                                                            @click="$dispatch('confirm-dialog', {
                                                    title: $el.dataset.title,
                                                    message: $el.dataset.message,
                                                    confirmText: $el.dataset.confirm,
                                                    cancelText: $el.dataset.cancel,
                                                    confirmColor: 'danger',
                                                    icon: 'fa-solid fa-trash',
                                                    wireMethod: 'trash',
                                                    wireParams: [parseInt($el.dataset.id)],
                                                    wireComponent: $el.dataset.component
                                                })"
                                                        >
                                                            <span class="fa-solid fa-trash"></span>
                                                            <span class="tooltip">{{ __('fm::labels.trash') }}</span>
                                                        </button>
                                                    @endif
                                                @else
                                                    <button
                                                        class="btn-icon has-tooltip group text-xs"
                                                        type="button"
                                                        data-title="{{ __('fm::labels.restore_title') }}"
                                                        data-message="{{ __('fm::labels.restore_message', ['name' => $file->filename]) }}"
                                                        data-confirm="{{ __('fm::labels.yes_restore') }}"
                                                        data-cancel="{{ __('fm::labels.cancel') }}"
                                                        data-id="{{ $file->id }}"
                                                        data-component="{{ $this->getId() }}"
                                                        @click="$dispatch('confirm-dialog', {
                                                    title: $el.dataset.title,
                                                    message: $el.dataset.message,
                                                    confirmText: $el.dataset.confirm,
                                                    cancelText: $el.dataset.cancel,
                                                    confirmColor: 'info',
                                                    icon: 'fa-solid fa-undo',
                                                    wireMethod: 'restore',
                                                    wireParams: [parseInt($el.dataset.id)],
                                                    wireComponent: $el.dataset.component
                                                })"
                                                    >
                                                        <span class="fa-solid fa-undo"></span>
                                                        <span class="tooltip">{{ __('fm::labels.restore') }}</span>
                                                    </button>
                                                    @if ($this->canDo('delete'))
                                                        <button
                                                            class="btn-icon danger has-tooltip group text-xs"
                                                            type="button"
                                                            data-title="{{ __('fm::labels.purge_title') }}"
                                                            data-message="{{ __('fm::labels.purge_message', ['name' => $file->filename]) }}"
                                                            data-confirm="{{ __('fm::labels.yes_purge') }}"
                                                            data-cancel="{{ __('fm::labels.cancel') }}"
                                                            data-id="{{ $file->id }}"
                                                            data-component="{{ $this->getId() }}"
                                                            @click="$dispatch('confirm-dialog', {
                                                    title: $el.dataset.title,
                                                    message: $el.dataset.message,
                                                    confirmText: $el.dataset.confirm,
                                                    cancelText: $el.dataset.cancel,
                                                    confirmColor: 'danger',
                                                    icon: 'fa-solid fa-fire',
                                                    wireMethod: 'purge',
                                                    wireParams: [parseInt($el.dataset.id)],
                                                    wireComponent: $el.dataset.component
                                                })"
                                                        >
                                                            <span class="fa-solid fa-fire"></span>
                                                            <span class="tooltip">{{ __('fm::labels.purge') }}</span>
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Image Preview Modal --}}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80"
        x-show="previewOpen"
        x-cloak
        x-transition
        @click="previewOpen = false"
    >
        <div
            class="relative max-h-[90vh] max-w-[90vw]"
            @click.stop
        >
            <button
                class="absolute -right-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-white text-gray-800 shadow-lg hover:bg-gray-100"
                type="button"
                @click="previewOpen = false"
            >
                <span class="fa-solid fa-times text-sm"></span>
            </button>
            <img
                :src="previewUrl"
                alt="Preview"
                class="max-h-[85vh] max-w-[85vw] rounded-lg object-contain shadow-2xl"
            >
        </div>
    </div>

    {{-- Rename Modal --}}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        x-show="renameOpen"
        x-cloak
        x-transition
        @click.self="renameOpen = false"
    >
        <div
            class="w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-gray-200 p-5 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('fm::labels.rename_file') }}
                </h3>
                <button
                    type="button"
                    @click="renameOpen = false"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <span class="fa-solid fa-times"></span>
                </button>
            </div>
            <div class="p-6">
                <div class="form-box required">
                    <label>{{ __('fm::labels.new_name') }}</label>
                    <input
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
            <div class="flex justify-end gap-3 border-t border-gray-200 p-5 dark:border-gray-700">
                <button
                    class="btn secondary"
                    type="button"
                    @click="renameOpen = false"
                >
                    {{ __('fm::labels.cancel') }}
                </button>
                <button
                    class="btn primary"
                    type="button"
                    wire:click="confirmRename"
                >
                    <span class="fa-solid fa-save mr-1"></span>
                    {{ __('fm::labels.save') }}
                </button>
            </div>
        </div>
    </div>

    {{-- New Folder Modal --}}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        x-show="newFolderOpen"
        x-cloak
        x-transition
        @click.self="newFolderOpen = false"
    >
        <div
            class="w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-gray-200 p-5 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('fm::labels.new_folder') }}
                </h3>
                <button
                    type="button"
                    @click="newFolderOpen = false"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <span class="fa-solid fa-times"></span>
                </button>
            </div>
            <div class="p-6">
                <div class="form-box required">
                    <label>{{ __('fm::labels.folder_name') }}</label>
                    <input
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
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('fm::labels.folder_name_help') }}
                    </p>
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-200 p-5 dark:border-gray-700">
                <button
                    class="btn secondary"
                    type="button"
                    @click="newFolderOpen = false"
                >
                    {{ __('fm::labels.cancel') }}
                </button>
                <button
                    class="btn primary"
                    type="button"
                    wire:click="createFolder"
                >
                    <span class="fa-solid fa-folder-plus mr-1"></span>
                    {{ __('fm::labels.create') }}
                </button>
            </div>
        </div>
    </div>

    {{-- Move / Copy Modal --}}
    <div
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
        x-show="moveOpen"
        x-cloak
        x-transition
        @click.self="moveOpen = false"
    >
        <div
            class="w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
            @click.stop
        >
            <div class="flex items-center justify-between border-b border-gray-200 p-5 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    <span x-show="$wire.moveAction === 'move'">{{ __('fm::labels.move_files') }}</span>
                    <span x-show="$wire.moveAction === 'copy'">{{ __('fm::labels.copy_files') }}</span>
                </h3>
                <button
                    type="button"
                    @click="moveOpen = false"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                >
                    <span class="fa-solid fa-times"></span>
                </button>
            </div>
            <div class="space-y-4 p-6">
                <div class="form-box required">
                    <label>{{ __('fm::labels.target_folder') }}</label>
                    <select
                        class="form-input"
                        wire:model.live="moveTargetFolder"
                    >
                        @foreach ($this->folders as $folder)
                            <option value="{{ $folder['path'] }}">{{ $folder['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-box">
                    <label>{{ __('fm::labels.target_sub_path') }}</label>
                    <input
                        class="form-input"
                        type="text"
                        wire:model="moveTargetSubPath"
                        placeholder="{{ __('fm::labels.target_sub_path_placeholder') }}"
                    >
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('fm::labels.target_sub_path_help') }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-200 p-5 dark:border-gray-700">
                <button
                    class="btn secondary"
                    type="button"
                    @click="moveOpen = false"
                >
                    {{ __('fm::labels.cancel') }}
                </button>
                <button
                    class="btn primary"
                    type="button"
                    wire:click="executeMoveOrCopy"
                >
                    <span
                        class="fa-solid fa-arrows-alt mr-1"
                        x-show="$wire.moveAction === 'move'"
                    ></span>
                    <span
                        class="fa-solid fa-copy mr-1"
                        x-show="$wire.moveAction === 'copy'"
                    ></span>
                    <span x-show="$wire.moveAction === 'move'">{{ __('fm::labels.move') }}</span>
                    <span x-show="$wire.moveAction === 'copy'">{{ __('fm::labels.copy') }}</span>
                </button>
            </div>
        </div>
    </div>

</div>

<script nonce="{{ csp_nonce() }}">
    (() => {
        const register = () => {
        Alpine.data('fmFileManager', (pageName) => ({
            pageName,
            renameOpen: false,
            newFolderOpen: false,
            moveOpen: false,
            previewUrl: null,
            previewOpen: false,
            dragging: false,
            dragCounter: 0,
            handleDrop(e) {
                this.dragCounter = 0;
                this.dragging = false;
                const files = Array.from(e.dataTransfer.files);
                if (!files.length) return;
                this.$wire.uploadMultiple('uploadFiles', files,
                    () => {},
                    () => {},
                    () => {}
                );
            },
            handleDragEnter() {
                this.dragCounter++;
                this.dragging = true;
            },
            handleDragLeave() {
                this.dragCounter--;
                if (this.dragCounter === 0) {
                    this.dragging = false;
                }
            },
            showPreview(url) {
                this.previewUrl = url;
                this.previewOpen = true;
            },
        }));
        };
    document.addEventListener('alpine:init', register);
    if (window.Alpine) {
        register();
    }
    })();
</script>

@php
    /**
     * Helper to get file icon class.
     * Declared here to be available via $this->fileIcon() in the blade scope.
     */
    if (!function_exists('fmFileIcon')) {
        function fmFileIcon(string $ext): string
        {
            return match (strtolower($ext)) {
                'pdf' => 'fa-solid fa-file-pdf text-red-500',
                'doc', 'docx' => 'fa-solid fa-file-word text-blue-500',
                'xls', 'xlsx' => 'fa-solid fa-file-excel text-green-600',
                'ppt', 'pptx' => 'fa-solid fa-file-powerpoint text-orange-500',
                'zip', 'rar', '7z', 'tar', 'gz' => 'fa-solid fa-file-zipper text-yellow-600',
                'mp4', 'avi', 'mov', 'mkv', 'webm' => 'fa-solid fa-file-video text-purple-500',
                'mp3', 'wav', 'ogg' => 'fa-solid fa-file-audio text-indigo-500',
                'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => 'fa-solid fa-file-image text-pink-500',
                'txt' => 'fa-solid fa-file-lines text-gray-500',
                default => 'fa-solid fa-file text-gray-400',
            };
        }
    }
@endphp

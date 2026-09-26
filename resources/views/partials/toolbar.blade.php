{{--
    FM toolbar: search row + path row. Shared by file-manager and file-picker.
    @param string $mode 'manager'|'picker'
    @param array $folders fm.json roots [{path, name}]
    @param string $currentFolder
    @param string $rootName
    @param array $segments [{name, path}]
    @param bool $locked picker locked to one root
    @param bool $showTrash
    @param string $sortBy, $sortDir, $viewMode
    @param array $can {upload, mkdir, rename, move, copy, delete}
    @param int $clipboardCount
    @param string $uploadInputId
--}}
<div class="fm-toolbar">
    <div class="fm-toolbar-row">
        <button
            class="fm-icon-btn fm-tree-trigger"
            type="button"
            aria-label="{{ __('fm::labels.toggle_folders') }}"
            title="{{ __('fm::labels.toggle_folders') }}"
            x-on:click="toggleSidebar()"
        ><i class="ph ph-tree-structure" aria-hidden="true"></i></button>

        <label class="fm-search">
            <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
            <input
                type="search"
                placeholder="{{ __('fm::labels.search_placeholder') }}"
                aria-label="{{ __('fm::labels.search_placeholder') }}"
                wire:model.live.debounce.300ms="search"
            >
        </label>

        @if ($mode === 'manager')
            <div class="fm-dropdown" data-dropdown="sort" x-on:click.outside="closeDropdownFor($el)">
                <button
                    class="fm-icon-btn"
                    type="button"
                    aria-haspopup="menu"
                    aria-label="{{ __('fm::labels.sort') }}"
                    title="{{ __('fm::labels.sort') }}"
                    :aria-expanded="isDropdown('sort') ? 'true' : 'false'"
                    x-on:click="toggleDropdown($event)"
                ><i class="ph ph-arrows-down-up" aria-hidden="true"></i></button>
                <div class="fm-menu fm-menu-anchored" role="menu" x-show="isDropdown('sort')" x-cloak>
                    @foreach (['filename' => 'sort_name', 'size' => 'sort_size', 'extension' => 'sort_type', 'created_at' => 'sort_date'] as $column => $labelKey)
                        <button
                            class="fm-menu-item {{ $sortBy === $column ? 'is-active' : '' }}"
                            type="button"
                            role="menuitemradio"
                            aria-checked="{{ $sortBy === $column ? 'true' : 'false' }}"
                            wire:click="setSort('{{ $column }}')"
                            x-on:click="closeDropdown()"
                        >
                            {{ __('fm::labels.'.$labelKey) }}
                            @if ($sortBy === $column)
                                <i class="ph ph-check fm-menu-kbd" aria-hidden="true"></i>
                            @endif
                        </button>
                    @endforeach
                    <div class="fm-menu-sep"></div>
                    @foreach (['asc' => 'sort_asc', 'desc' => 'sort_desc'] as $direction => $labelKey)
                        <button
                            class="fm-menu-item {{ $sortDir === $direction ? 'is-active' : '' }}"
                            type="button"
                            role="menuitemradio"
                            aria-checked="{{ $sortDir === $direction ? 'true' : 'false' }}"
                            wire:click="setSortDir('{{ $direction }}')"
                            x-on:click="closeDropdown()"
                        >
                            {{ __('fm::labels.'.$labelKey) }}
                            @if ($sortDir === $direction)
                                <i class="ph ph-check fm-menu-kbd" aria-hidden="true"></i>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>

            <button
                class="fm-icon-btn"
                type="button"
                aria-label="{{ $viewMode === 'grid' ? __('fm::labels.list_view') : __('fm::labels.grid_view') }}"
                title="{{ $viewMode === 'grid' ? __('fm::labels.list_view') : __('fm::labels.grid_view') }}"
                wire:click="toggleView"
            ><i class="ph {{ $viewMode === 'grid' ? 'ph-list' : 'ph-squares-four' }}" aria-hidden="true"></i></button>
        @endif

        <button
            class="fm-icon-btn"
            type="button"
            aria-label="{{ __('fm::labels.refresh') }}"
            title="{{ __('fm::labels.refresh') }}"
            wire:click="$refresh"
        ><i class="ph ph-arrow-clockwise" aria-hidden="true"></i></button>
    </div>

    <div class="fm-toolbar-row">
        <nav class="fm-path" aria-label="{{ __('fm::labels.path') }}">
            @if ($showTrash)
                <span class="fm-path-current"><i class="ph ph-trash" aria-hidden="true"></i>{{ __('fm::labels.trash') }}</span>
            @else
                @if (! $locked && count($folders) > 1)
                    <div class="fm-dropdown" data-dropdown="storage" x-on:click.outside="closeDropdownFor($el)">
                        <button
                            class="fm-path-link fm-path-root"
                            type="button"
                            data-fm-storage-switcher
                            aria-haspopup="menu"
                            aria-label="{{ __('fm::labels.storage') }}: {{ $rootName }}"
                            :aria-expanded="isDropdown('storage') ? 'true' : 'false'"
                            x-on:click="toggleDropdown($event)"
                        >
                            <i class="ph ph-hard-drives" aria-hidden="true"></i>{{ $rootName }}<i class="ph ph-caret-down" aria-hidden="true"></i>
                        </button>
                        <div class="fm-menu fm-menu-anchored fm-menu-left" role="menu" x-show="isDropdown('storage')" x-cloak>
                            @foreach ($folders as $folder)
                                <button
                                    class="fm-menu-item {{ $folder['path'] === $currentFolder ? 'is-active' : '' }}"
                                    type="button"
                                    role="menuitemradio"
                                    aria-checked="{{ $folder['path'] === $currentFolder ? 'true' : 'false' }}"
                                    data-path="{{ $folder['path'] }}"
                                    x-on:click="switchFolderFrom($event)"
                                ><i class="ph ph-hard-drives" aria-hidden="true"></i>{{ $folder['name'] }}</button>
                            @endforeach
                        </div>
                    </div>
                @else
                    <button class="fm-path-link fm-path-root" type="button" data-path="" x-on:click="go($event)">
                        <i class="ph ph-hard-drives" aria-hidden="true"></i>{{ $rootName }}
                    </button>
                @endif

                @foreach ($segments as $segment)
                    <i class="ph ph-caret-right fm-path-sep" aria-hidden="true"></i>
                    @if ($loop->last)
                        <span class="fm-path-current" aria-current="page">{{ $segment['name'] }}</span>
                    @else
                        <button class="fm-path-link" type="button" data-path="{{ $segment['path'] }}" x-on:click="go($event)">{{ $segment['name'] }}</button>
                    @endif
                @endforeach
            @endif
        </nav>

        <div class="fm-toolbar-actions">
            @if (! $showTrash)
                @canAccess('fm.manage.create')
                    @if ($mode === 'manager' && $can['mkdir'])
                        <button
                            class="fm-icon-btn"
                            type="button"
                            aria-label="{{ __('fm::labels.new_folder') }}"
                            title="{{ __('fm::labels.new_folder') }}"
                            x-on:click="newFolderHere()"
                        ><i class="ph ph-folder-plus" aria-hidden="true"></i></button>
                    @elseif ($mode === 'picker' && $can['mkdir'])
                        {{--
                            Picker: inline popover instead of a nested modal. Deliberately NOT a
                            <form> — the picker usually sits inside a page form, and a nested
                            form would be dropped by the parser (Enter would submit the page).
                        --}}
                        <div class="fm-dropdown" data-dropdown="newfolder" x-on:click.outside="closeDropdownFor($el)">
                            <button
                                class="fm-icon-btn"
                                type="button"
                                aria-haspopup="dialog"
                                aria-label="{{ __('fm::labels.new_folder') }}"
                                title="{{ __('fm::labels.new_folder') }}"
                                :aria-expanded="isDropdown('newfolder') ? 'true' : 'false'"
                                x-on:click="toggleDropdown($event)"
                            ><i class="ph ph-folder-plus" aria-hidden="true"></i></button>
                            <div
                                class="fm-menu fm-menu-anchored fm-popover"
                                role="dialog"
                                aria-label="{{ __('fm::labels.new_folder') }}"
                                x-show="isDropdown('newfolder')"
                                x-cloak
                                x-on:keydown.escape.stop="closeDropdown()"
                            >
                                <label class="fm-popover-label" for="{{ $uploadInputId }}-folder">{{ __('fm::labels.folder_name') }}</label>
                                <div class="fm-popover-row">
                                    <input
                                        id="{{ $uploadInputId }}-folder"
                                        class="fm-popover-input"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="{{ __('fm::labels.folder_name_placeholder') }}"
                                        wire:model="newFolderName"
                                        x-on:keydown.enter.prevent="$wire.createFolder()"
                                    >
                                    <button
                                        class="fm-btn is-primary"
                                        type="button"
                                        aria-label="{{ __('fm::labels.create') }}"
                                        title="{{ __('fm::labels.create') }}"
                                        wire:click="createFolder"
                                    ><i class="ph ph-check" aria-hidden="true"></i></button>
                                </div>
                                @error('newFolderName')
                                    <p class="fm-popover-error">{{ $message }}</p>
                                @enderror
                                <p class="fm-popover-help">{{ __('fm::labels.folder_name_help') }}</p>
                            </div>
                        </div>
                    @endif
                @endcanAccess

                @if ($mode === 'manager' && $clipboardCount > 0 && ($can['copy'] || $can['move']))
                    <button
                        class="fm-icon-btn"
                        type="button"
                        aria-label="{{ __('fm::labels.paste_count', ['count' => $clipboardCount]) }}"
                        title="{{ __('fm::labels.paste_count', ['count' => $clipboardCount]) }} (Ctrl+V)"
                        wire:click="paste"
                    >
                        <i class="ph ph-clipboard" aria-hidden="true"></i>
                        <span class="fm-icon-btn-badge">{{ $clipboardCount }}</span>
                    </button>
                @endif

                @canAccess('fm.manage.create')
                    @if ($can['upload'])
                        <label class="fm-btn is-primary" for="{{ $uploadInputId }}">
                            <i class="ph ph-upload-simple" aria-hidden="true"></i>
                            <span class="fm-hide-sm">{{ __('fm::labels.upload') }}</span>
                        </label>
                    @endif
                @endcanAccess
            @endif
        </div>
    </div>
</div>

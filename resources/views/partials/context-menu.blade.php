{{-- Right-click / ⋯ menu (manager). One instance, positioned by fmBrowser. @param bool $showTrash  @param array $can --}}
<div
    class="fm-menu"
    role="menu"
    aria-label="{{ __('fm::labels.actions') }}"
    x-ref="menu"
    x-show="menu.open"
    x-cloak
    :style="menuStyle"
    x-on:click.outside="closeMenu()"
    x-on:keydown.escape.prevent.stop="closeMenu()"
    x-on:keydown.arrow-down.prevent.stop="menuFocus(1)"
    x-on:keydown.arrow-up.prevent.stop="menuFocus(-1)"
    x-on:scroll.window="closeMenu()"
    x-on:resize.window="closeMenu()"
>
    @if (! $showTrash)
        <div x-show="isFileMenu">
            <button class="fm-menu-item" type="button" role="menuitem" x-show="canPreview" x-on:click="menuPreview()">
                <i class="ph ph-eye" aria-hidden="true"></i>{{ __('fm::labels.preview') }}<span class="fm-menu-kbd">Space</span>
            </button>
            <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuDetails()">
                <i class="ph ph-info" aria-hidden="true"></i>{{ __('fm::labels.details') }}
            </button>
            <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuDownload()">
                <i class="ph ph-download-simple" aria-hidden="true"></i>{{ __('fm::labels.download') }}
            </button>
            @if ($can['rename'])
                <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuRename()">
                    <i class="ph ph-pencil-simple" aria-hidden="true"></i>{{ __('fm::labels.rename') }}<span class="fm-menu-kbd">F2</span>
                </button>
            @endif
            @if ($can['copy'] || $can['move'])
                <div class="fm-menu-sep"></div>
            @endif
            @if ($can['copy'])
                <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuCopy()">
                    <i class="ph ph-copy" aria-hidden="true"></i>{{ __('fm::labels.copy') }}<span class="fm-menu-kbd">Ctrl C</span>
                </button>
            @endif
            @if ($can['move'])
                <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuCut()">
                    <i class="ph ph-scissors" aria-hidden="true"></i>{{ __('fm::labels.cut') }}<span class="fm-menu-kbd">Ctrl X</span>
                </button>
            @endif
            @if ($can['delete'])
                <div class="fm-menu-sep"></div>
                <button class="fm-menu-item is-danger" type="button" role="menuitem" x-on:click="menuTrash()">
                    <i class="ph ph-trash" aria-hidden="true"></i>{{ __('fm::labels.trash') }}<span class="fm-menu-kbd">Del</span>
                </button>
            @endif
        </div>

        <div x-show="isDirMenu">
            <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuOpen()">
                <i class="ph ph-folder-open" aria-hidden="true"></i>{{ __('fm::labels.open') }}<span class="fm-menu-kbd">Enter</span>
            </button>
            @canAccess('fm.manage.create')
                @if ($can['mkdir'])
                    <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuNewFolder()">
                        <i class="ph ph-folder-plus" aria-hidden="true"></i>{{ __('fm::labels.new_folder') }}
                    </button>
                @endif
            @endcanAccess
            @if ($can['copy'] || $can['move'])
                <button class="fm-menu-item" type="button" role="menuitem" :disabled="!hasClipboard" x-on:click="menuPaste()">
                    <i class="ph ph-clipboard" aria-hidden="true"></i>{{ __('fm::labels.paste') }}<span class="fm-menu-kbd">Ctrl V</span>
                </button>
            @endif
            <div class="fm-menu-sep"></div>
            {{-- Folder copy/cut/trash: UI only until the folder backend lands. --}}
            <button class="fm-menu-item" type="button" role="menuitem" disabled title="{{ __('fm::labels.coming_soon') }}">
                <i class="ph ph-copy" aria-hidden="true"></i>{{ __('fm::labels.copy') }}
            </button>
            <button class="fm-menu-item" type="button" role="menuitem" disabled title="{{ __('fm::labels.coming_soon') }}">
                <i class="ph ph-scissors" aria-hidden="true"></i>{{ __('fm::labels.cut') }}
            </button>
            <div class="fm-menu-sep"></div>
            <button class="fm-menu-item is-danger" type="button" role="menuitem" disabled title="{{ __('fm::labels.coming_soon') }}">
                <i class="ph ph-trash" aria-hidden="true"></i>{{ __('fm::labels.trash') }}
            </button>
        </div>

        <div x-show="isBlankMenu">
            @canAccess('fm.manage.create')
                @if ($can['mkdir'])
                    <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuNewFolder()">
                        <i class="ph ph-folder-plus" aria-hidden="true"></i>{{ __('fm::labels.new_folder') }}
                    </button>
                @endif
            @endcanAccess
            @if ($can['copy'] || $can['move'])
                <button class="fm-menu-item" type="button" role="menuitem" :disabled="!hasClipboard" x-on:click="menuPaste()">
                    <i class="ph ph-clipboard" aria-hidden="true"></i>{{ __('fm::labels.paste') }}<span class="fm-menu-kbd">Ctrl V</span>
                </button>
            @endif
            @canAccess('fm.manage.create')
                @if ($can['upload'])
                    <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuUpload()">
                        <i class="ph ph-upload-simple" aria-hidden="true"></i>{{ __('fm::labels.upload') }}
                    </button>
                @endif
            @endcanAccess
        </div>
    @else
        <div x-show="isFileMenu">
            <button class="fm-menu-item" type="button" role="menuitem" x-on:click="menuRestore()">
                <i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i>{{ __('fm::labels.restore') }}
            </button>
            @if ($can['delete'])
                <button class="fm-menu-item is-danger" type="button" role="menuitem" x-on:click="menuPurge()">
                    <i class="ph ph-fire" aria-hidden="true"></i>{{ __('fm::labels.delete_permanently') }}<span class="fm-menu-kbd">Del</span>
                </button>
            @endif
        </div>
    @endif
</div>

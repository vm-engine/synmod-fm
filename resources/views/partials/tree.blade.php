{{--
    Sidebar folder tree (lazily expanded) + optional Trash entry pinned bottom.
    @param array $tree nodes from BrowsesFolders::folderTree
    @param string $rootName
    @param string $subPath
    @param bool $showTrash
    @param bool $withTrash
--}}
<aside class="fm-sidebar" :class="sidebarOpen ? 'is-open' : ''" aria-label="{{ __('fm::labels.folders') }}">
    <ul class="fm-tree" role="tree">
        <li role="treeitem" aria-expanded="true">
            <div class="fm-tree-node">
                <span class="fm-tree-spacer"></span>
                <button
                    class="fm-tree-link {{ ! $showTrash && $subPath === '' ? 'is-current' : '' }}"
                    type="button"
                    data-path=""
                    x-on:click="go($event)"
                >
                    <i class="ph ph-hard-drives fm-tree-icon" aria-hidden="true"></i><span>{{ $rootName }}</span>
                </button>
            </div>
            @if ($tree !== [])
                @include('fm::partials.tree-nodes', ['nodes' => $tree, 'subPath' => $subPath, 'showTrash' => $showTrash, 'depth' => 1])
            @endif
        </li>
    </ul>

    @if ($withTrash)
        <div class="fm-sidebar-foot">
            <button
                class="fm-tree-link {{ $showTrash ? 'is-current' : '' }}"
                type="button"
                wire:click="toggleTrash"
            >
                <i class="ph ph-trash fm-tree-icon" aria-hidden="true"></i><span>{{ __('fm::labels.trash') }}</span>
            </button>
        </div>
    @endif
</aside>
<div class="fm-sidebar-backdrop" x-show="sidebarOpen" x-cloak x-on:click="closeSidebar()"></div>

{{-- @param array $nodes  @param string $subPath  @param bool $showTrash  @param int $depth --}}
<ul class="fm-tree-children" role="group">
    @foreach ($nodes as $node)
        <li role="treeitem" aria-expanded="{{ $node['open'] ? 'true' : 'false' }}" wire:key="fm-tree-{{ $node['path'] }}">
            <div class="fm-tree-node" style="--fm-depth: {{ $depth }}">
                @if ($node['open'] && $node['children'] === [])
                    <span class="fm-tree-spacer"></span>
                @else
                    <button
                        class="fm-tree-toggle"
                        type="button"
                        data-path="{{ $node['path'] }}"
                        aria-label="{{ $node['open'] ? __('fm::labels.collapse') : __('fm::labels.expand') }} {{ $node['name'] }}"
                        x-on:click="toggleNodeFrom($event)"
                    ><i class="ph {{ $node['open'] ? 'ph-caret-down' : 'ph-caret-right' }}" aria-hidden="true"></i></button>
                @endif
                <button
                    class="fm-tree-link {{ ! $showTrash && $subPath === $node['path'] ? 'is-current' : '' }}"
                    type="button"
                    data-path="{{ $node['path'] }}"
                    x-on:click="go($event)"
                >
                    <i class="ph ph-folder fm-tree-icon fm-tone-folder" aria-hidden="true"></i><span>{{ $node['name'] }}</span>
                </button>
            </div>
            @if ($node['open'] && ! empty($node['children']))
                @include('fm::partials.tree-nodes', ['nodes' => $node['children'], 'subPath' => $subPath, 'showTrash' => $showTrash, 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>

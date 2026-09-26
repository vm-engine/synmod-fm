{{--
    Grid tile for a folder or file (FmItem array).
    @param array $item  @param string $mode  @param bool $selected  @param bool $cut  @param bool $showLocation
--}}
<div
    class="fm-tile fm-tone-{{ $item['tone'] }} {{ $selected ? 'is-selected' : '' }} {{ $cut ? 'is-cut' : '' }}"
    role="option"
    tabindex="0"
    aria-selected="{{ $selected ? 'true' : 'false' }}"
    data-fm-item
    data-kind="{{ $item['kind'] }}"
    data-key="{{ $item['key'] }}"
    data-name="{{ $item['name'] }}"
    data-url="{{ $item['url'] }}"
    data-preview="{{ $item['preview'] }}"
    wire:key="fm-tile-{{ $item['kind'] }}-{{ $item['key'] }}"
    x-on:click="onItemClick($event)"
    x-on:dblclick="onItemOpen($event)"
    x-on:keydown.enter.prevent="onItemOpen($event)"
    @if ($mode === 'manager') x-on:contextmenu.prevent.stop="openItemMenu($event)" @endif
>
    @if ($mode === 'manager')
        <button
            class="fm-tile-check"
            type="button"
            tabindex="-1"
            aria-label="{{ __('fm::labels.select_item', ['name' => $item['name']]) }}"
            x-on:click.stop="onItemToggle($event)"
        ><i class="ph ph-check" aria-hidden="true"></i></button>
        <button
            class="fm-tile-more"
            type="button"
            aria-haspopup="menu"
            aria-label="{{ __('fm::labels.more_actions', ['name' => $item['name']]) }}"
            x-on:click.stop="openItemMenuFromButton($event)"
        ><i class="ph ph-dots-three" aria-hidden="true"></i></button>
    @endif

    <div
        class="fm-tile-thumb"
        @if ($item['thumbKind'] !== '')
            data-fm-thumb="{{ $item['thumbKind'] }}"
            data-fm-thumb-id="{{ $item['key'] }}"
            data-fm-thumb-src="{{ $item['url'] }}"
        @endif
    >
        @if ($item['thumb'])
            <img class="fm-tile-img" src="{{ $item['thumb'] }}" alt="" loading="lazy">
        @else
            <i class="ph {{ $item['icon'] }} fm-tile-icon" aria-hidden="true"></i>
        @endif
    </div>
    <div class="fm-tile-body">
        <div class="fm-tile-name" title="{{ $item['name'] }}">{{ $item['name'] }}</div>
        <div class="fm-tile-meta">
            @if ($showLocation)
                {{ __('fm::labels.in_location', ['path' => $item['location'] !== '' ? $item['location'] : '/']) }}
            @elseif ($item['kind'] === 'dir')
                {{ __('fm::labels.folder') }}
            @else
                {{ $item['size'] }}
            @endif
        </div>
    </div>
</div>

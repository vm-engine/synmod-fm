{{-- List-view row. @param array $item  @param bool $selected  @param bool $cut  @param bool $inTrash --}}
<tr
    class="fm-row {{ $selected ? 'is-selected' : '' }} {{ $cut ? 'is-cut' : '' }}"
    tabindex="0"
    aria-selected="{{ $selected ? 'true' : 'false' }}"
    data-fm-item
    data-kind="{{ $item['kind'] }}"
    data-key="{{ $item['key'] }}"
    data-name="{{ $item['name'] }}"
    data-url="{{ $item['url'] }}"
    data-preview="{{ $item['preview'] }}"
    wire:key="fm-row-{{ $item['kind'] }}-{{ $item['key'] }}"
    x-on:click="onItemClick($event)"
    x-on:dblclick="onItemOpen($event)"
    x-on:keydown.enter.prevent="onItemOpen($event)"
    x-on:contextmenu.prevent.stop="openItemMenu($event)"
>
    <td class="fm-col-check">
        <button
            class="fm-tile-check"
            type="button"
            tabindex="-1"
            aria-label="{{ __('fm::labels.select_item', ['name' => $item['name']]) }}"
            x-on:click.stop="onItemToggle($event)"
        ><i class="ph ph-check" aria-hidden="true"></i></button>
    </td>
    <td>
        <div class="fm-row-name">
            <span class="fm-row-icon fm-tone-{{ $item['tone'] }}">
                @if ($item['thumb'])
                    <img src="{{ $item['thumb'] }}" alt="" loading="lazy">
                @else
                    <i class="ph {{ $item['icon'] }}" aria-hidden="true"></i>
                @endif
            </span>
            <span class="fm-row-text" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
        </div>
    </td>
    <td class="fm-col-type">{{ $item['kind'] === 'dir' ? __('fm::labels.folder') : $item['ext'] }}</td>
    <td>
        @if ($inTrash && $item['kind'] === 'file')
            {{ __('fm::labels.in_location', ['path' => $item['location'] !== '' ? $item['location'] : '/']) }}
        @else
            {{ $item['size'] !== '' ? $item['size'] : '—' }}
        @endif
    </td>
    <td class="fm-col-date">{{ $item['date'] !== '' ? $item['date'] : '—' }}</td>
    <td class="fm-col-more">
        <button
            class="fm-tile-more"
            type="button"
            aria-haspopup="menu"
            aria-label="{{ __('fm::labels.more_actions', ['name' => $item['name']]) }}"
            x-on:click.stop="openItemMenuFromButton($event)"
        ><i class="ph ph-dots-three" aria-hidden="true"></i></button>
    </td>
</tr>

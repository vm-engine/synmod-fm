{{-- @param string $search  @param bool $showTrash  @param array $can  @param string $uploadInputId  @param string $mode --}}
<div class="fm-empty">
    @if ($search !== '')
        <i class="ph ph-magnifying-glass fm-empty-icon" aria-hidden="true"></i>
        <p class="fm-empty-title">{{ __('fm::labels.no_results', ['q' => $search]) }}</p>
    @elseif ($showTrash)
        <i class="ph ph-trash fm-empty-icon" aria-hidden="true"></i>
        <p class="fm-empty-title">{{ __('fm::labels.trash_empty') }}</p>
    @else
        <i class="ph ph-folder-open fm-empty-icon" aria-hidden="true"></i>
        <p class="fm-empty-title">{{ __('fm::labels.folder_empty') }}</p>
        @canAccess('fm.manage.create')
            <div class="fm-empty-actions">
                @if ($can['upload'])
                    <label class="fm-btn is-primary" for="{{ $uploadInputId }}">
                        <i class="ph ph-upload-simple" aria-hidden="true"></i>{{ __('fm::labels.upload') }}
                    </label>
                @endif
                @if ($mode === 'manager' && $can['mkdir'])
                    <button class="fm-btn" type="button" x-on:click="newFolderHere()">
                        <i class="ph ph-folder-plus" aria-hidden="true"></i>{{ __('fm::labels.new_folder') }}
                    </button>
                @endif
            </div>
        @endcanAccess
    @endif
</div>

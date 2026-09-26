{{-- Right slide-over with file details (manager). @param \VmEngine\Fm\Models\FmFile|null $file  @param array $can --}}
@if ($file)
    @php($style = \VmEngine\Fm\Support\FileTypeStyle::for($file->extension))
    @php($absoluteUrl = url($file->getUrl()))
    <aside class="fm-drawer" role="dialog" aria-labelledby="fm-drawer-title" wire:key="fm-drawer-{{ $file->id }}">
        <header class="fm-drawer-head">
            <h3 class="fm-drawer-title" id="fm-drawer-title">{{ __('fm::labels.details') }}</h3>
            <button class="fm-icon-btn" type="button" aria-label="{{ __('fm::labels.close') }}" title="{{ __('fm::labels.close') }} (Esc)" wire:click="closeDetails">
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        </header>

        <div class="fm-drawer-scroll">
            <div class="fm-drawer-preview fm-tone-{{ $style['tone'] }}">
                @if ($file->isImage())
                    <img src="{{ $file->getUrl() }}" alt="{{ $file->filename }}">
                @elseif ($file->isVideo())
                    <video src="{{ $file->getUrl() }}" controls preload="metadata"></video>
                @else
                    <i class="ph {{ $style['icon'] }}" aria-hidden="true"></i>
                @endif
            </div>

            <div class="fm-drawer-body">
                <p class="fm-drawer-name">{{ $file->filename }}</p>
                <dl class="fm-meta">
                    <dt>{{ __('fm::labels.type') }}</dt>
                    <dd>{{ strtoupper($file->extension) }} · {{ $file->mime_type }}</dd>
                    <dt>{{ __('fm::labels.size') }}</dt>
                    <dd>{{ $file->getHumanSize() }}</dd>
                    @if ($file->dimensions())
                        <dt>{{ __('fm::labels.dimensions') }}</dt>
                        <dd>{{ $file->dimensions() }}</dd>
                    @endif
                    <dt>{{ __('fm::labels.created') }}</dt>
                    <dd>{{ $file->created_at->format('d M Y H:i') }}</dd>
                    @if ($file->creator)
                        <dt>{{ __('fm::labels.uploaded_by') }}</dt>
                        <dd>{{ $file->creator->name }}</dd>
                    @endif
                    <dt>{{ __('fm::labels.location') }}</dt>
                    <dd>{{ $file->getStoragePath() }}</dd>
                </dl>
                <div class="fm-url">
                    <input type="text" readonly value="{{ $absoluteUrl }}" aria-label="{{ __('fm::labels.url') }}">
                    <button class="fm-icon-btn" type="button" data-url="{{ $absoluteUrl }}"
                        aria-label="{{ __('fm::labels.copy_url') }}" title="{{ __('fm::labels.copy_url') }}"
                        x-on:click="copyUrl($event)"
                    ><i class="ph ph-link" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>

        <footer class="fm-drawer-actions">
            <a class="fm-icon-btn" href="{{ $file->getUrl() }}" download="{{ $file->filename }}"
                aria-label="{{ __('fm::labels.download') }}" title="{{ __('fm::labels.download') }}"
            ><i class="ph ph-download-simple" aria-hidden="true"></i></a>
            @if (! $file->is_trashed && $can['rename'])
                <button class="fm-icon-btn" type="button" wire:click="startRename({{ $file->id }})"
                    aria-label="{{ __('fm::labels.rename') }}" title="{{ __('fm::labels.rename') }} (F2)"
                ><i class="ph ph-pencil-simple" aria-hidden="true"></i></button>
            @endif
            @if (! $file->is_trashed && $can['delete'])
                <button class="fm-icon-btn is-danger" type="button" data-id="{{ $file->id }}" data-name="{{ $file->filename }}"
                    aria-label="{{ __('fm::labels.trash') }}" title="{{ __('fm::labels.trash') }}"
                    x-on:click="confirmTrashFrom($event)"
                ><i class="ph ph-trash" aria-hidden="true"></i></button>
            @endif
        </footer>
    </aside>
@endif

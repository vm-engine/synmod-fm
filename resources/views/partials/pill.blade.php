{{--
    Floating selection pill (manager).
    @param array $summary {files:int, dirs:int, bytes:int, single:?FmFile}
    @param bool $showTrash
    @param array $can
--}}
@php($noFiles = $summary['files'] === 0)
<div class="fm-pill" role="toolbar" aria-label="{{ __('fm::labels.selection') }}">
    <div class="fm-pill-info" aria-live="polite">
        @if ($summary['single'])
            <span class="fm-pill-name" title="{{ $summary['single']->filename }}">{{ $summary['single']->filename }}</span>
            <span class="fm-pill-muted">· {{ $summary['single']->getHumanSize() }}</span>
            @if ($summary['single']->dimensions())
                <span class="fm-pill-muted">· {{ $summary['single']->dimensions() }}</span>
            @endif
        @else
            @if ($summary['files'] > 0)
                <span class="fm-pill-name">{{ trans_choice('fm::labels.n_files', $summary['files']) }}</span>
            @endif
            @if ($summary['dirs'] > 0)
                <span class="{{ $summary['files'] > 0 ? 'fm-pill-muted' : 'fm-pill-name' }}">{{ $summary['files'] > 0 ? '· ' : '' }}{{ trans_choice('fm::labels.n_folders', $summary['dirs']) }}</span>
            @endif
            @if ($summary['files'] > 0)
                <span class="fm-pill-muted">· {{ \VmEngine\Fm\Models\FmFile::formatBytes($summary['bytes']) }}</span>
            @endif
        @endif
    </div>

    <span class="fm-pill-sep" aria-hidden="true"></span>

    @if (! $showTrash)
        @if ($can['copy'])
            <button class="fm-pill-btn" type="button" wire:click="clipboardCopy" @disabled($noFiles)
                aria-label="{{ __('fm::labels.copy') }}"
                title="{{ $noFiles ? __('fm::labels.coming_soon') : __('fm::labels.copy').' (Ctrl+C)' }}"
            ><i class="ph ph-copy" aria-hidden="true"></i></button>
        @endif
        @if ($can['move'])
            <button class="fm-pill-btn" type="button" wire:click="clipboardCut" @disabled($noFiles)
                aria-label="{{ __('fm::labels.cut') }}"
                title="{{ $noFiles ? __('fm::labels.coming_soon') : __('fm::labels.cut').' (Ctrl+X)' }}"
            ><i class="ph ph-scissors" aria-hidden="true"></i></button>
        @endif
        @if ($can['delete'])
            <button class="fm-pill-btn is-danger" type="button" x-on:click="confirmTrashSelected()" @disabled($noFiles)
                aria-label="{{ __('fm::labels.trash') }}"
                title="{{ $noFiles ? __('fm::labels.coming_soon') : __('fm::labels.trash').' (Del)' }}"
            ><i class="ph ph-trash" aria-hidden="true"></i></button>
        @endif
    @else
        <button class="fm-pill-btn" type="button" wire:click="restoreSelected"
            aria-label="{{ __('fm::labels.restore') }}" title="{{ __('fm::labels.restore') }}"
        ><i class="ph ph-arrow-counter-clockwise" aria-hidden="true"></i></button>
        @if ($can['delete'])
            <button class="fm-pill-btn is-danger" type="button" x-on:click="confirmPurgeSelected()"
                aria-label="{{ __('fm::labels.delete_permanently') }}" title="{{ __('fm::labels.delete_permanently') }} (Del)"
            ><i class="ph ph-fire" aria-hidden="true"></i></button>
        @endif
    @endif

    <span class="fm-pill-sep" aria-hidden="true"></span>

    <button class="fm-pill-btn" type="button" wire:click="clearSelection"
        aria-label="{{ __('fm::labels.clear') }}" title="{{ __('fm::labels.clear') }} (Esc)"
    ><i class="ph ph-x" aria-hidden="true"></i></button>
</div>

{{-- Shown when fm.json has no usable storage root (never fall back to the disk root). @param string $class --}}
<div class="{{ $class }}">
    <div class="fm-empty">
        <i
            class="ph ph-hard-drives fm-empty-icon"
            aria-hidden="true"
        ></i>
        <p class="fm-empty-title">{{ __('fm::labels.not_configured') }}</p>
        <p>{{ __('fm::labels.not_configured_help') }}</p>
    </div>
</div>

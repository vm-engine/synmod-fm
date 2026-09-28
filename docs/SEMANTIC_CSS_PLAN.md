# Semantic CSS Plan — synmod-fm

> **Superseded (2.0.0 redesign):** the file manager and picker now own their styling in `resources/dist/fm.css`/`fm.js` (Phosphor icons). The Move/Copy modal and the `<x-synapse-lightbox>` preview were replaced by the clipboard and the full-screen viewer, and the FM-only `.syn-file-tile*`, `.syn-folder-chip*`, `.syn-drop-overlay*`, `.syn-segmented*`, `.syn-file-thumb-sm`, `.syn-file-icon-sm` classes were removed from synapse. Kept for history.

**Status: done.** Implemented per `.claude/plans/2026-09-18-synmod-fm-semantic-css.md`, using the same tier-0/1/2 method as `packages/synapps-auth`, `synmod-queue` and `synmod-cms`. It did not follow the `syn-fm-*`/dedicated-file convention originally proposed below. fm has no CSS entry of its own, and most of the patterns turned out to be generic.

## What actually happened

- **Modals:** rename, new folder and move/copy in `file-manager`, plus the `file-picker` modal, now use `<x-synapse-modal>`. The internal events were renamed to `open-modal-fm-*` / `close-modal-fm-*`. The picker's public events (`fm-picker-open[-key]`, `fm:open-picker`, `fm-picker-close`) are unchanged; its Alpine `init()` forwards them to the modal. The picker keeps `z-[9999]` on the modal root so it still stacks above parent modals.
- **Image preview:** now uses a new synapse component, `<x-synapse-lightbox name="fm-preview" />`, opened with `$dispatch('open-lightbox-fm-preview', { url })`.
- **Tier 1:** the search inputs use `<x-synapse-search-box>`, the panels use `.syn-panel`, and the list view uses `datatable-col-checkbox`/`datatable-col-actions`.
- **New generic classes** in synapse's `synapse-components.css`:
  - `.syn-modal-header/-title/-close/-body/-footer`
  - `.form-help`
  - `.syn-breadcrumb-link/-sep`
  - `.syn-section-label`
  - `.syn-folder-chip*`
  - `.syn-empty-state*`
  - `.syn-drop-overlay*`
  - `.syn-segmented*`
  - `.syn-selection-bar-card/-count`
  - `.syn-row-selected`
  - `.syn-file-tile*`
  - `.syn-file-thumb-sm`, `.syn-file-icon-sm`
  - `.syn-sort-header`
- **Left alone:** FontAwesome icons and sort icons, same scope rule as the queue and cms passes.
- **Tailwind v4 trap:** `.syn-*` rules are unlayered, so an inline utility can't override them. Variants therefore got their own modifier classes (`-sm`, `-selected`, `-pickable`, `-check-on`).

## Result

| File | utility tokens in `class=` (before → after) |
| --- | --- |
| `file-manager.blade.php` | 800 → 449 |
| `file-picker.blade.php` | 273 → 156 |

## Original inventory (for history)

| File | `class=` occurrences (before) |
| --- | --- |
| `resources/views/components/file-manager/file-manager.blade.php` | 187 |
| `resources/views/components/file-picker/file-picker.blade.php` | 61 |

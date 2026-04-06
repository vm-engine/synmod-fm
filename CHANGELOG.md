# Changelog

## [1.0.3] - 2026-04-06

### Fixed
- Made `create_fm_files_table` migration idempotent — `up()` now wraps `Schema::create()` in a `Schema::hasTable()` guard so the migration is safe to run multiple times without errors

## [1.0.2] - 2026-04-04

### Added
- `folder` parameter on `file-picker` component to lock the picker to a specific folder
- Validation of the given folder against `fm.json` config on mount — shows an inline error if the folder path is not registered
- When locked to a folder, the folder selector dropdown is hidden and navigation is restricted to that folder only
- `invalid_folder` translation key in `lang/en/labels.php`

## [1.0.1] - 2026-03-16

### Added
- `FmSetup` artisan command (`fm:setup`) for interactive `fm.json` configuration
- Ability to update existing `fm.json` without losing other config keys

## [1.0.0] - 2026-03-16

### Added
- Initial release of the File Manager module
- Backend file manager UI with grid and list views
- Per-folder ACL via `synapps/config/fm.json` with role-based permissions
- Dev user bypass (`$user->isDev()`) for all actions
- `FmAction` enum for type-safe action checks
- Soft-delete trash with configurable auto-purge
- GD-based image thumbnail generation
- Drag-and-drop file upload support
- File download button in grid and list views
- Search files by name
- Sort files by name, size, type, and date (asc/desc)
- Refresh button for manual file list reload
- View toggle between grid and list modes
- Bulk selection with move, copy, and trash actions
- Rename and create folder support
- Image preview modal
- Reusable `file-picker` Livewire component for forms
- TinyMCE integration via `fm-tinymce.js`
- File streaming API endpoint
- Database-backed file metadata (`fm_files` table)
- `FmConfig` helper for reading `fm.json` configuration
- `FileManagerService` for all file operations
- `ThumbnailService` for GD-based thumbnail generation
- Host-agnostic file URLs (path-only, no hardcoded APP_URL)

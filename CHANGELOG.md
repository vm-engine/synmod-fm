# Changelog

## [1.0.10] - 2026-07-22

### Fixed
- **Mobile-responsive `file-manager` toolbar** — the search input, upload/new-folder/trash-toggle buttons, folder selector, and file grid now stack/reflow properly on small screens instead of overflowing horizontally; file grid drops to a single column below the `sm` breakpoint.

### Added
- `README.md` — package overview, feature list, and usage documentation (previously undocumented).

### Documentation
- `CLAUDE.md` documents `FmFile::isImage()`/`isVideo()`, the activity-logging action list (`fm.file.upload`/`trash`/`restore`/`purge`/`rename`/`move`/`copy`, `fm.folder.create`), and `file-picker` props (`folder`, `accept`, `pickerKey`) plus how to open a picker from Alpine/JS.

## [1.0.9] - 2026-06-30

### Fixed
- `FileManagerService::upload()` now throws a `RuntimeException` when `Storage::putFileAs()` returns `false`, preventing a silent failure where the file record was saved to the database without the file being written to disk
- `FileManagerServiceTest`: pinned `FmConfig` in-memory config to a known-good array in `beforeEach` instead of setting it to `null`, fixing a parallel test race condition where `FmSetupCommandTest` writing `my_disk` to `fm.json` could corrupt the config mid-test

## [1.0.8] - 2026-06-29

### Changed
- Removed hardcoded `"version"` field from `composer.json` so Composer derives the package version from git tags, fixing version detection on production servers

## [1.0.7] - 2026-06-26

### Added
- `FmFile::isVideo()` — checks if `mime_type` starts with `video/`
- `file-picker` component now filters the file list server-side using the `accept` attribute: supports wildcard mime types (`image/*`, `video/*`), extension lists (`.jpg,.png`), and exact mime types (`application/pdf`); replaced the previous blade-only partial filtering
- `fm:open-picker` Alpine event listener on `file-picker` supports `key` scoping and dynamic `accept` override, enabling multiple pickers on the same page
- `module.json` now includes `"is_package": true`

### Changed
- SVGs and images without a generated thumbnail are now displayed as `<img>` in the grid and list views of both `file-manager` and `file-picker`, instead of falling back to the file icon — SVGs render as proper vector previews at any size without a separate thumbnail file
- Video files (`mp4`, `webm`, etc.) show a purple `fa-circle-play` icon placeholder in grid and list views instead of a generic file icon
- `webm` added to the `fileIcon()` helper video extension map

### Fixed
- Move operation thumbnail bug: when moving files with thumbnails, the old thumbnail path was never saved before updating the file model, causing `Storage::disk()->move(newPath, newPath)` — thumbnails are now correctly relocated to the target directory

## [1.0.6] - 2026-04-28

### Added
- Activity logging for every user-initiated `FileManagerService` action via `SynAuth::logActivity` (module = `fm`):
  - `fm.file.upload` — feature `file`
  - `fm.file.trash` — soft delete to trash
  - `fm.file.restore` — restore from trash
  - `fm.file.purge` — permanent deletion
  - `fm.file.rename` — includes old → new name
  - `fm.file.move` — single summary entry per batch with target path and file list
  - `fm.file.copy` — same batch summary
  - `fm.folder.create` — feature `folder`
- Private `FileManagerService::log()` helper centralises the call and silently skips when no user context is available, so unauthenticated API uploads and the scheduled trash-purge command keep working without violating the `user_activities.user_id NOT NULL` constraint.

## [1.0.5] - 2026-04-07

### Added
- Indonesian (`id`) translations for all language files (`menu.php`, `acl.php`, `labels.php`)

### Fixed
- Removed stale `@phpstan-ignore-next-line` comments in `FmConfig` that no longer had matching errors
- Fixed parallel test race condition in `FmConfigTest` — `beforeEach` now removes `fm.json` to isolate default-value assertions from concurrent `FmSetupCommandTest` writes

## [1.0.4] - 2026-04-07

### Changed
- Moved Sort, Refresh, and View Toggle controls from the top toolbar into a dedicated file list header row, split into left (sort) and right (refresh + view toggle) groups for better visual organization
- Improved grid view action overlay: buttons now have a consistent `inline-flex` size (`h-5 w-5`) and the overlay container has a frosted background, rounded corners, and shadow for improved readability

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

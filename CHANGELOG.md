# Changelog

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

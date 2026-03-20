# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Package Identity

- **Package:** `vm-engine/synmod-fm`
- **Namespace:** `VmEngine\Fm\`
- **Module ID:** `fm`
- **ACL key:** `fm.manage`
- **Route prefix:** `/admin/fm` (via `web.backend.php`)

## Commands

Run all tools from the **main Laravel project root** (`/srv/www/synapse/`), not from inside this package:

```bash
# Static analysis (run BEFORE pint)
vendor/bin/phpstan analyse packages/synmod-fm/src --level=5

# Code style
vendor/bin/pint packages/synmod-fm/

# Tests
./vendor/bin/pest --filter="FmConfig"           # single test file
./vendor/bin/pest packages/synmod-fm/tests/     # all package tests
```

## Architecture

### Configuration: `FmConfig`
All runtime config is read from `synapps/config/fm.json` (not a Laravel config file). `FmConfig` is a static class that loads/caches the JSON. Always call `FmConfig::reload()` in tests to reset the in-memory cache between test cases.

**fm.json structure:**
```json
{
    "disk": "public",
    "folders": [
        {
            "path": "fm/public",
            "label": "Public Files",
            "permissions": {
                "admin": ["read", "upload", "delete", "rename", "move", "copy", "mkdir"]
            }
        }
    ],
    "upload": { "max_size_kb": 10240, "allowed_extensions": ["jpg", "png", "pdf"] },
    "image": { "thumbnail_width": 200, "thumbnail_height": 200 },
    "trash": { "auto_purge_days": 30 }
}
```

### ACL System
Permissions are **folder-scoped** and **role-based**, configured in `fm.json` — not using the standard Synapse ACL system. The `fm.manage` ACL in `module.json` only controls access to the backend route. Within the FM, `FmConfig::canUserDo($user, $folderPath, FmAction::Upload)` checks per-folder role permissions. Dev users (`$user->isDev()`) bypass all checks.

### FmAction Enum
Type-safe actions: `Read`, `Upload`, `Delete`, `Rename`, `Move`, `Copy`, `Mkdir`. Always use `FmAction::tryFrom()` when converting strings.

### File Storage Model
`FmFile` stores metadata in `fm_files` table. Key path fields:
- `folder_path` — top-level folder (e.g. `fm/public`)
- `relative_path` — path within folder including subdirs (e.g. `docs/report.pdf`)
- `disk` — Laravel storage disk name

Full storage path = `folder_path + '/' + relative_path`. `getStoragePath()` computes this. URLs are **path-only** (no host) via `parse_url($url, PHP_URL_PATH)` to remain host-agnostic.

### Thumbnail Generation
`ThumbnailService` uses PHP GD (no Intervention Image). Thumbnails are stored alongside the original with `_thumb` suffix (e.g. `photo_thumb.jpg`). PNG/WebP transparency is preserved. Thumbnails are only generated when `extension_loaded('gd')` is true — tests with `Storage::fake()` will skip thumbnail generation since GD cannot decode fake file contents.

### Livewire Components (MFC pattern)
Both components use the **MFC (Multi-File Component)** pattern — logic in `.php`, template in `.blade.php`, same folder name:

- `file-manager/` — Full backend file manager. Registered as `<livewire:fm::file-manager />`.
- `file-picker/` — Embeddable modal picker for forms. Registered as `<livewire:fm::file-picker />`.

`file-picker` is `#[Modelable]` — bind with `wire:model` to get back the selected file URL.

### TinyMCE Integration
`resources/js/fm-tinymce.js` exports a config object to merge into `tinymce.init()`. It listens for `fm:file-selected` (dispatched by `file-picker`) and calls the TinyMCE callback. Import and spread into your TinyMCE init options.

### API Endpoints
- `POST /api/fm/upload` — Upload via Sanctum auth. Returns JSON with `url`, `thumbnail_url`, etc.
- `GET /api/fm/stream/{id}` — Stream file content (for protected/private disks). Checks `FmAction::Read` permission.

### Tests
Tests use `Storage::fake('public')` and reset `FmConfig` cache via `ReflectionProperty` in `beforeEach`. Always use `RefreshDatabase` when creating `FmFile` records.

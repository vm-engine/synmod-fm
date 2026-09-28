# synmod-fm

File Manager module for [Laravel Synapse](https://github.com/vm-engine/synapse). Provides a backend file browser, an embeddable form picker, per-folder role-based ACL, GD-based thumbnail generation, and a streaming API for uploads from other applications.

**Package:** `vm-engine/synmod-fm`
**Module ID:** `fm`
**Requires:** PHP 8.2+, Laravel 12, Livewire 4, `vm-engine/synapse` ^3.0, `vm-engine/synapps-auth` ^2.0

---

## Features

- **Backend File Manager** — sidebar folder tree (lazy expand, Trash pinned), grid and list views, drag-and-drop upload, sort by name/size/type/date, rename, create folder, details drawer; remembers the last folder across visits
- **Recursive Search** — searches the current folder and everything below it (file and folder names), showing each result's location; capped at `FileManagerService::SEARCH_LIMIT` (100) results with a "refine your search" hint
- **Context Menu & Clipboard** — right-click / ⋯ menu on every item, copy/cut/paste between folders (replaces the old Move/Copy modal), floating selection pill (name · size · dimensions, or count · total size)
- **Keyboard Shortcuts** — Space (preview), F2 (rename), Del (trash, or purge in Trash), Ctrl/⌘+C/X/V/A, arrows (move focus), Esc (close menu/drawer, then clear selection)
- **Full-Screen Viewer** — images, videos and PDFs (Space / double-click / Preview): PDFs render all pages lazily with page counter and zoom (50–300%); ←/→ steps through the previewable files of the listing; Esc closes
- **Compress to .zip** — zips the selected files and folders into the current folder (context menu or selection pill; requires the Upload permission)
- **Per-Folder ACL** — permissions configured in `synapps/config/fm.json`, not the standard Synapse ACL system — each folder declares which roles may `read`/`upload`/`delete`/`rename`/`move`/`copy`/`mkdir` within it; dev users bypass all checks
- **Soft-Delete Trash** — trashed files are hidden, not removed, until purged (manually or via a scheduled command you wire up) after a configurable number of days
- **Thumbnails** — images via GD on the server (no Intervention Image dependency; PNG/WebP transparency preserved; skipped when the `gd` extension isn't loaded); PDFs and videos rendered **in the browser** (pdf.js / `<video>` frame) on first view and stored server-side — no Imagick/ffmpeg required
- **Image Previews** — SVGs and un-thumbnailed images render as real `<img>` previews; other files show a tinted file-type tile (Phosphor icons)
- **Reusable `file-picker` Component** — embeddable modal picker for any form (click to pick, double-click or **Choose** to select, inline "New folder", full-screen viewer); can be locked to a single folder, filtered by MIME type/extension (server-side), and scoped so multiple pickers can coexist on one page
- **TinyMCE Integration** — `resources/js/fm-tinymce.js` wires the picker into TinyMCE's image/media insertion flow
- **Activity Logging** — every user-initiated action (upload, trash, restore, purge, rename, move, copy, compress, folder create) is recorded via `synapps-auth`'s activity log
- **API Upload & Streaming** — `POST /api/fm/upload` and `GET /api/fm/stream/{id}`, both Sanctum-authenticated, for server-to-server uploads and protected-disk file serving
- **Host-Agnostic URLs** — file/thumbnail URLs are path-only (no hardcoded `APP_URL`), so they work correctly behind reverse proxies or when the app domain changes

---

## Installation

This is a private package, not published on Packagist — add its VCS repository to your project's `composer.json` **before** requiring it, so Composer knows where to fetch it from:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "ssh://git@repo.4visionmedia.com:40288/vm-engine/synmod-fm.git"
        }
    ],
    "require": {
        "vm-engine/synmod-fm": "^1.0"
    }
}
```

Then install and discover:

```bash
composer update vm-engine/synmod-fm
php artisan synapps:discover
php artisan migrate
```

Run the interactive setup wizard to create (or update) `synapps/config/fm.json`:

```bash
php artisan fm:setup
```

This prompts for the storage disk, thumbnail dimensions, max upload size, allowed extensions, trash auto-purge days, and lets you add/edit folders with per-role permissions. Re-running it updates the existing file without discarding other keys.

### `fm.json` structure

```json
{
    "disk": "public",
    "folders": [
        {
            "path": "fm/public",
            "name": "Public Files",
            "permissions": {
                "admin": ["read", "upload", "delete", "rename", "move", "copy", "mkdir"],
                "editor": ["read", "upload"]
            }
        }
    ],
    "upload": {
        "max_size_kb": 10240,
        "allowed_extensions": ["jpg", "jpeg", "png", "gif", "webp", "svg", "pdf", "doc", "docx", "xls", "xlsx", "zip", "txt"]
    },
    "image": { "thumbnail_width": 200, "thumbnail_height": 200 },
    "trash": { "auto_purge_days": 30 }
}
```

Not a Laravel config file — it's read at runtime via `synapps_path('config/fm.json')` by the `FmConfig` static class, which caches it in memory. Call `FmConfig::reload()` to force a re-read (also used to reset the cache between test cases).

---

## Backend Routes

Single backend route under `/admin/fm`, gated by the `fm.manage` ACL entry:

```php
// routes/web.backend.php
Route::livewire('/', 'fm::file-manager')->name('index')
    ->middleware('can-access:fm.manage');
```

`routes/web.php` only serves the package-owned assets — `GET /fm/assets/{file}` (route `fm.assets`, `AssetController`) delivers `fm.css`, `fm.js` and the vendored pdf.js build from `resources/dist/`. The components load them via Livewire `@assets`, so the host app needs no build step for the file manager.

---

## ACL

The `fm.manage` permission (declared in `module.json`) only controls whether a user can reach the backend `/admin/fm` route at all — it is a single CRUD-style entry, not folder-scoped.

**Actual file permissions are folder-scoped and role-based**, configured entirely in `fm.json` (see above), and checked via `FmConfig::canUserDo()` / `FmConfig::getAllowedActions()`. This is a deliberate departure from the standard Synapse `module.json` ACL system, since per-folder role grants don't map cleanly onto the fixed create/read/update/delete shape.

```php
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Enums\FmAction;

if (FmConfig::canUserDo($user, 'fm/public', FmAction::Upload)) {
    // ...
}

$allowed = FmConfig::getAllowedActions($user, 'fm/public'); // array<FmAction>
```

Dev users (`$user->isDev()`) bypass both the `fm.manage` gate and all per-folder checks.

### `FmAction` enum

Type-safe action values: `Read`, `Upload`, `Delete`, `Rename`, `Move`, `Copy`, `Mkdir`. Use `FmAction::tryFrom($string)` when converting from raw config/request strings — never compare against the raw string values directly.

---

## Components

### `<livewire:fm::file-manager />`

Full backend file browser (registered automatically, mounted at `/admin/fm`). MFC pattern — `file-manager.php` (logic) + `file-manager.blade.php` (template) in the same directory.

Handles: folder tree + breadcrumbs, recursive search, sort, grid/list toggle, Trash view, bulk select, upload, trash/restore/purge (single + bulk), rename, create folder, clipboard copy/cut/paste, compress, details drawer and the full-screen viewer. All actions are gated per-folder via `FmConfig::canUserDo()` before executing, and id-based actions only load files from the currently browsed storage root.

### `<livewire:fm::file-picker />`

Embeddable modal picker for use inside any form. `#[Modelable]` — bind with `wire:model` to receive the selected file's URL back.

```blade
<livewire:fm::file-picker wire:model="coverImageUrl" />

{{-- Locked to one folder, images only, with a scoping key for multi-picker pages --}}
<livewire:fm::file-picker
    wire:model="galleryImageUrl"
    folder="fm/gallery"
    accept="image/*"
    picker-key="gallery-image"
/>
```

| Prop | Type | Description |
|---|---|---|
| `value` | string | The bound value (via `wire:model`) — the selected file's URL |
| `folder` | string | Lock the picker to one `fm.json`-registered folder path; an unregistered path shows an inline error instead of the browser, and hides the folder-selector dropdown |
| `accept` | string | MIME/extension filter applied **server-side**: wildcard (`image/*`, `video/*`), extension list (`.jpg,.png`), exact MIME type, or `*` (default, no filter) |
| `pickerKey` | string | Scopes the `fm:open-picker` / `fm:file-selected` browser events so multiple pickers can coexist on the same page |
| `label` | string | Label displayed above the input |

In the picker, click marks a file, double-click or **Choose** selects it, and Space or the Preview button opens the full-screen viewer. When locked to a `folder`, the picker remembers the last sub-folder visited.

Opening a picker programmatically (e.g. from a TinyMCE toolbar button or a custom Alpine component):

```js
window.dispatchEvent(new CustomEvent('fm:open-picker', {
    detail: { accept: 'image/*', key: 'gallery-image' } // key must match the target picker's picker-key prop; omit both for a single unscoped picker
}));
```

`fm:file-selected` is dispatched back with `{ url, path, fileId, key }` — listen for it if you need more than the bound `wire:model` value (e.g. the raw storage path or file ID).

### TinyMCE Integration

```js
import fmTinyMceConfig from '../../vendor/vm-engine/synmod-fm/resources/js/fm-tinymce.js';

tinymce.init({
    // ...your config
    ...fmTinyMceConfig,
});
```

Listens for `fm:file-selected` (dispatched by `file-picker`) and inserts the selected file into the editor via TinyMCE's image/media callback.

---

## API Endpoints

Both routes require Sanctum authentication (`auth:sanctum`).

### `POST /api/fm/upload`

```
file          required|file (validated against fm.json's max_size_kb/allowed_extensions)
folder_path   required|string
sub_path      nullable|string
```

Checks `FmAction::Upload` on `folder_path` for the authenticated user before storing; returns `403` if denied.

```json
{
    "id": 42,
    "filename": "report.pdf",
    "url": "/storage/fm/public/report.pdf",
    "thumbnail_url": "/storage/fm/public/report.pdf",
    "size": 204800,
    "human_size": "200 KB",
    "mime_type": "application/pdf"
}
```

### `GET /api/fm/stream/{id}`

Streams the file's raw content with the correct `Content-Type` — checks `FmAction::Read` on the file's folder for the authenticated user. Use this for files on protected/private disks that shouldn't be served via a public storage symlink.

---

## `FileManagerService`

All file operations flow through this service — the Livewire components and `FileController` both call it rather than touching `Storage`/`FmFile` directly.

```php
use VmEngine\Fm\Services\FileManagerService;

$service = app(FileManagerService::class);

$service->listDirectory(string $folderPath, string $subPath = '', bool $showTrash = false, string $search = '', string $sortBy = 'filename', string $sortDir = 'asc'): array; // ['dirs' => [...], 'files' => Collection, 'truncated' => bool]; a search is recursive, capped at FileManagerService::SEARCH_LIMIT
$service->listTrash(string $folderPath, string $search = '', string $sortBy = 'filename', string $sortDir = 'asc'): Collection; // all trashed files under a storage root
$service->upload(UploadedFile $file, string $folderPath, string $subPath = '', ?int $createdBy = null): FmFile;
$service->delete(FmFile $file): void;      // soft delete (trash)
$service->restore(FmFile $file): void;
$service->purge(FmFile $file): void;       // permanent delete (storage + thumbnail + DB row)
$service->rename(FmFile $file, string $newName): FmFile;
$service->move(array $fileIds, string $targetFolderPath, string $targetSubPath = ''): void;
$service->copy(array $fileIds, string $targetFolderPath, string $targetSubPath = ''): void;
$service->compress(array $fileIds, array $dirs, string $folderPath, string $subPath, ?int $createdBy): ?FmFile; // null when nothing on disk to zip
$service->createFolder(string $parentFolderPath, string $subPath, string $name): void;
$service->getUrl(FmFile $file): string;
$service->purgeExpiredTrash(): int;        // purges everything past fm.json's trash.auto_purge_days; returns count purged
```

`upload()` throws `RuntimeException` if the underlying disk write fails (`Storage::putFileAs()` returns `false`), instead of silently creating an `FmFile` DB record for a file that was never actually written.

**Filenames are made unique automatically** — uploading/moving/copying a file whose name already exists in the target path appends `_1`, `_2`, etc., rather than overwriting.

`compress()` zips the given files and directories (recursively, active files only; entry paths relative to the current folder) into `{name}.zip` for a single item or `archive.zip` for several, saved in the current folder.

**There is no bundled scheduled command for `purgeExpiredTrash()`** — call it from your own scheduled command if you want automatic trash cleanup, e.g.:

```php
// routes/console.php or a custom command
Schedule::call(fn () => app(FileManagerService::class)->purgeExpiredTrash())->daily();
```

### Activity logging

Every write method above logs via `SynAuth::logActivity()` (module `fm`), through a private `log()` helper that silently no-ops when there's no user context (unauthenticated API uploads, a scheduled purge job) — `user_activities.user_id` is `NOT NULL`, so logging simply doesn't fire rather than throwing.

| Action | Feature | Notes |
|---|---|---|
| `fm.file.upload` | `file` | |
| `fm.file.trash` | `file` | soft delete |
| `fm.file.restore` | `file` | |
| `fm.file.purge` | `file` | permanent deletion |
| `fm.file.rename` | `file` | includes old → new name |
| `fm.file.move` | `file` | one summary entry per batch (target path + file list), not one per file |
| `fm.file.copy` | `file` | same batch-summary behavior |
| `fm.file.compress` | `file` | file count + archive path |
| `fm.folder.create` | `folder` | |

---

## `ThumbnailService`

GD-based thumbnail generation (no Intervention Image dependency) — called automatically by `FileManagerService::upload()`/`copy()` for image files.

- Image thumbnails are stored alongside the original with a `_thumb` suffix (e.g. `photo_thumb.jpg`)
- PNG/WebP transparency is preserved
- Dimensions come from `fm.json`'s `image.thumbnail_width` / `image.thumbnail_height`
- Silently skipped if `extension_loaded('gd')` is `false` — in tests using `Storage::fake()`, thumbnail generation is skipped since GD cannot decode fake file contents
- Permanently deleting a file (`purge()`) removes its thumbnail too

### Browser-rendered PDF & video thumbnails

PDFs and videos get thumbnails without any server software. For eligible files without a thumbnail (`FmFile::canHaveClientThumbnail()`), `FmItem` sets `thumbKind` (`pdf`|`video`); `fm.js` renders the visible ones lazily (pdf.js page 1 or a `<video>` frame, two at a time) and sends a JPEG data URL to the component's `storeThumbnail()`, which calls `ThumbnailService::storeClientThumbnail(FmFile $file, string $dataUrl): bool`.

- Accepted only if it is a real JPEG (checked with `getimagesizefromstring`), ≤ 300 KB and within 2× the configured thumbnail box
- Stored as `{filename}_thumb.jpg` (e.g. `report.pdf_thumb.jpg`), so `report.pdf` / `report.mp4` / `report.jpg` never share a thumbnail
- Never overwrites an existing file at the thumbnail path
- pdf.js (6.3.289 legacy build, Apache-2.0) is vendored in `resources/dist/` and loaded on demand

---

## `FmFile` Model

| Column | Type | Notes |
|---|---|---|
| `disk` | string | Laravel storage disk name |
| `folder_path` | string | Top-level configured folder (e.g. `fm/public`) |
| `relative_path` | string | Path within the folder, including sub-directories (e.g. `docs/report.pdf`) |
| `filename` | string | Stored filename (post-sanitization/uniqueness) |
| `original_name` | string | Original client filename |
| `extension` | string | Lowercased file extension |
| `mime_type` | string | |
| `size` | int | Bytes |
| `has_thumbnail` | bool | |
| `is_trashed` / `trashed_at` | bool / datetime | Soft-delete state |
| `created_by` | int, nullable | FK to the users table |

Full storage path = `folder_path + '/' + relative_path`, computed by `getStoragePath()`. Uses `WithDeleteToken` (from `vm-engine/synapse`) for CSRF-safe delete confirmations.

**Notable methods:**

| Method | Description |
|---|---|
| `getUrl()` / `getThumbnailUrl()` | Path-only public URL (host-agnostic); thumbnail URL falls back to the file URL when `has_thumbnail` is `false` |
| `getThumbnailPath()` | `_thumb` path for images, `{filename}_thumb.jpg` for browser-rendered PDF/video thumbnails |
| `isImage()` / `isVideo()` | Checks `mime_type` prefix (`image/`, `video/`) |
| `canHaveClientThumbnail()` | PDF or video — eligible for a browser-rendered thumbnail |
| `previewKind()` | `image` \| `video` \| `pdf` \| `null` — drives the full-screen viewer |
| `getHumanSize()` / `formatBytes(int)` | e.g. `"200 KB"`, `"1.5 MB"` (trailing `.0` dropped) |
| `dimensions()` | `"W×H"` when `width`/`height` attributes exist, else `null` (the table has no such columns yet, so currently always `null`) |
| `scopeActive()` / `scopeTrashed()` | Filter by `is_trashed` |
| `scopeInPath($folderPath, $subPath = '')` | Files directly within a folder/sub-path (non-recursive) |
| `scopeUnderPath($folderPath, $subPath = '')` | Files within a folder/sub-path and all its sub-directories (used by search and compress) |
| `scopeSearch($q)` | Matches `filename` or `original_name` |

---

## Testing

```bash
# From the main Laravel project root
./vendor/bin/pest packages/synmod-fm/tests/

# Filter by name
./vendor/bin/pest packages/synmod-fm/tests/ --filter="FileManagerService"
```

Tests use `Storage::fake('public')` and reset `FmConfig`'s in-memory cache in `beforeEach` (via `FmConfig::reload()` or reflection). Use `RefreshDatabase` when creating `FmFile` records.

## Code Quality

```bash
# From the main Laravel project root — static analysis FIRST, then Pint (level 7 via the package gate)
vendor/bin/phpstan analyse packages/synmod-fm/src -c packages/synmod-fm/phpstan.neon --memory-limit=1G
vendor/bin/pint packages/synmod-fm/
```

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Models\FmFile;
use VmEngine\SynAuth\Models\Role;
use VmEngine\SynAuth\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    // Pin FmConfig so parallel workers writing fm.json can't interfere.
    $this->pinConfig = function (array $adminPermissions = ['read', 'upload', 'delete', 'rename', 'move', 'copy', 'mkdir']): void {
        $ref = new ReflectionProperty(FmConfig::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue(null, [
            'disk' => 'public',
            'folders' => [
                ['path' => 'fm/public', 'name' => 'Public', 'permissions' => ['admin' => $adminPermissions]],
            ],
            'image' => ['thumbnail_width' => 200, 'thumbnail_height' => 200],
            'upload' => ['max_size_kb' => 10240, 'allowed_extensions' => ['jpg', 'png', 'pdf']],
            'trash' => ['auto_purge_days' => 30],
        ]);
    };
    ($this->pinConfig)();

    $devRole = Role::factory()->create(['name' => 'Developer', 'slug' => 'dev', 'level' => 0, 'can_access_backend' => true]);
    $this->user = User::factory()->create();
    $this->user->roles()->attach($devRole);

    $this->makeFile = function (string $relativePath, int $size = 1, bool $trashed = false): FmFile {
        Storage::disk('public')->put('fm/public/'.$relativePath, 'x');

        return FmFile::create([
            'disk' => 'public',
            'folder_path' => 'fm/public',
            'relative_path' => $relativePath,
            'filename' => basename($relativePath),
            'original_name' => basename($relativePath),
            'extension' => pathinfo($relativePath, PATHINFO_EXTENSION),
            'mime_type' => 'application/pdf',
            'size' => $size,
            'has_thumbnail' => false,
            'is_trashed' => $trashed,
            'trashed_at' => $trashed ? now() : null,
        ]);
    };

    $this->file = ($this->makeFile)('report.pdf');
});

afterEach(function () {
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, null);
});

// --- rendering -----------------------------------------------------------

it('renders the fm shell without the old move modal', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->assertOk()
        ->assertSeeHtml('pageName: &quot;File Manager&quot;')
        ->assertSeeHtml('class="fm"')
        ->assertSeeHtml('fmBrowser(')
        ->assertSeeHtml('fm-toolbar')
        ->assertSeeHtml('data-fm-item')
        ->assertSeeHtml('open-modal-fm-rename')
        ->assertDontSeeHtml('open-modal-fm-move')
        ->assertDontSeeHtml('syn-file-tile');
});

// --- rename / new folder (kept behaviour) --------------------------------

it('opens the rename modal via synapse modal event', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('startRename', $this->file->id)
        ->assertSet('renameName', 'report.pdf')
        ->assertDispatched('open-modal-fm-rename');
});

it('closes the rename modal after renaming', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('startRename', $this->file->id)
        ->set('renameName', 'renamed.pdf')
        ->call('confirmRename')
        ->assertDispatched('close-modal-fm-rename');

    expect($this->file->fresh()->filename)->toBe('renamed.pdf');
});

it('creates a folder in the current folder by default', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->set('newFolderName', 'docs')
        ->call('createFolder')
        ->assertDispatched('close-modal-fm-new-folder');

    Storage::disk('public')->assertExists('fm/public/docs');
});

it('creates a folder inside the folder chosen from the context menu', function () {
    Storage::disk('public')->makeDirectory('fm/public/docs');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->set('newFolderParent', 'docs')
        ->set('newFolderName', 'y2026')
        ->call('createFolder')
        ->assertSet('newFolderParent', null);

    Storage::disk('public')->assertExists('fm/public/docs/y2026');
});

it('refuses an unsafe new-folder parent', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->set('newFolderParent', '../escape')
        ->set('newFolderName', 'x')
        ->call('createFolder');

    Storage::disk('public')->assertMissing('fm/escape/x');
});

// --- selection -----------------------------------------------------------

it('selects files and folders exclusively or additively', function () {
    Storage::disk('public')->makeDirectory('fm/public/docs');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('selectOnly', 'file', (string) $this->file->id)
        ->assertSet('selected', [$this->file->id])
        ->assertSet('selectedDirs', [])
        ->call('toggleSelect', 'dir', 'docs')
        ->assertSet('selectedDirs', ['docs'])
        ->call('selectOnly', 'dir', 'docs')
        ->assertSet('selected', [])
        ->assertSet('selectedDirs', ['docs'])
        ->call('clearSelection')
        ->assertSet('selectedDirs', []);
});

it('summarises the selection with total size and a single-file shortcut', function () {
    $second = ($this->makeFile)('brief.pdf', 2000);
    $this->file->update(['size' => 1000]);

    $component = Livewire::actingAs($this->user)->test('fm::file-manager')
        ->set('selected', [$this->file->id, $second->id]);

    $summary = $component->instance()->selectionSummary;
    expect($summary['files'])->toBe(2)
        ->and($summary['bytes'])->toBe(3000)
        ->and($summary['single'])->toBeNull();

    $component->set('selected', [$second->id]);
    expect($component->instance()->selectionSummary['single']->id)->toBe($second->id);
});

// --- clipboard -----------------------------------------------------------

it('copies then pastes a duplicate into another folder', function () {
    Storage::disk('public')->makeDirectory('fm/public/archive');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('selectOnly', 'file', (string) $this->file->id)
        ->call('clipboardCopy')
        ->assertSet('clipboard.mode', 'copy')
        ->call('paste', 'archive')
        ->assertSet('clipboard.files', [$this->file->id]);

    expect(FmFile::count())->toBe(2)
        ->and(FmFile::where('relative_path', 'archive/report.pdf')->exists())->toBeTrue()
        ->and($this->file->fresh()->relative_path)->toBe('report.pdf');
});

it('cuts then pastes a move and clears the clipboard', function () {
    Storage::disk('public')->makeDirectory('fm/public/archive');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('selectOnly', 'file', (string) $this->file->id)
        ->call('clipboardCut')
        ->call('paste', 'archive')
        ->assertSet('clipboard.files', []);

    expect(FmFile::count())->toBe(1)
        ->and($this->file->fresh()->relative_path)->toBe('archive/report.pdf');
});

it('treats cut and paste into the same folder as a no-op', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('selectOnly', 'file', (string) $this->file->id)
        ->call('clipboardCut')
        ->call('paste')
        ->assertSet('clipboard.files', []);

    expect($this->file->fresh()->filename)->toBe('report.pdf')
        ->and(FmFile::count())->toBe(1);
});

it('keeps the clipboard while navigating', function () {
    Storage::disk('public')->makeDirectory('fm/public/archive');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('selectOnly', 'file', (string) $this->file->id)
        ->call('clipboardCopy')
        ->call('navigateTo', 'archive')
        ->assertSet('subPath', 'archive')
        ->assertSet('clipboard.files', [$this->file->id]);
});

it('refuses to paste into an unsafe target', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('selectOnly', 'file', (string) $this->file->id)
        ->call('clipboardCopy')
        ->call('paste', '../outside');

    expect(FmFile::count())->toBe(1);
});

it('refuses to paste without copy permission', function () {
    ($this->pinConfig)(['read']);
    Storage::disk('public')->makeDirectory('fm/public/archive');
    $adminRole = Role::factory()->create(['name' => 'Admin', 'slug' => 'admin', 'level' => 1, 'can_access_backend' => true]);
    $admin = User::factory()->create();
    $admin->roles()->attach($adminRole);

    Livewire::actingAs($admin)->test('fm::file-manager')
        ->set('selected', [$this->file->id])
        ->set('clipboard', ['mode' => 'copy', 'files' => [$this->file->id]])
        ->call('paste', 'archive')
        ->assertDispatched('notify');

    expect(FmFile::count())->toBe(1);
});

// --- tree + navigation ---------------------------------------------------

it('lists the tree lazily and toggles nodes', function () {
    Storage::disk('public')->makeDirectory('fm/public/a/b/c');
    Storage::disk('public')->makeDirectory('fm/public/z');

    $component = Livewire::actingAs($this->user)->test('fm::file-manager');
    $tree = $component->instance()->folderTree;

    expect(array_column($tree, 'name'))->toBe(['a', 'z'])
        ->and($tree[0]['children'])->toBeNull();

    $component->call('toggleNode', 'a');
    $tree = $component->instance()->folderTree;
    expect($tree[0]['open'])->toBeTrue()
        ->and(array_column($tree[0]['children'], 'name'))->toBe(['b']);

    $component->call('toggleNode', 'a/b')->call('toggleNode', 'a')
        ->assertSet('expanded', []);
});

it('expands the current folder and its ancestors when navigating', function () {
    Storage::disk('public')->makeDirectory('fm/public/a/b');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('navigateTo', 'a/b')
        ->assertSet('subPath', 'a/b')
        ->assertSet('expanded', ['a', 'a/b']);
});

it('ignores traversal and missing folders when navigating', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('navigateTo', '../secret')
        ->assertSet('subPath', '')
        ->call('navigateTo', 'missing')
        ->assertSet('subPath', '');

    Storage::disk('public')->assertMissing('fm/public/missing');
});

it('remembers the last folder and expands only its ancestors on return', function () {
    Storage::disk('public')->makeDirectory('fm/public/a/b');
    Storage::disk('public')->makeDirectory('fm/public/z');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('toggleNode', 'z')
        ->call('navigateTo', 'a/b');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->assertSet('subPath', 'a/b')
        ->assertSet('expanded', ['a', 'a/b']);
});

it('falls back to the root when the remembered folder is gone', function () {
    Storage::disk('public')->makeDirectory('fm/public/a/b');

    Livewire::actingAs($this->user)->test('fm::file-manager')->call('navigateTo', 'a/b');
    Storage::disk('public')->deleteDirectory('fm/public/a');

    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->assertSet('subPath', '')
        ->assertSet('expanded', []);
});

// --- trash + details -----------------------------------------------------

it('lists all trashed files of the storage in trash view', function () {
    ($this->makeFile)('docs/old.pdf', 1, true);
    ($this->makeFile)('gone.pdf', 1, true);

    $component = Livewire::actingAs($this->user)->test('fm::file-manager')->call('toggleTrash');

    expect(array_column($component->instance()->items, 'name'))->toBe(['gone.pdf', 'old.pdf']);
});

it('opens and closes the details drawer', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('showDetails', $this->file->id)
        ->assertSet('detailsId', $this->file->id)
        ->assertSet('selected', [$this->file->id])
        ->assertSeeHtml('fm-drawer')
        ->call('closeDetails')
        ->assertSet('detailsId', null);
});

it('lists folders before files', function () {
    Storage::disk('public')->makeDirectory('fm/public/docs');

    $items = Livewire::actingAs($this->user)->test('fm::file-manager')->instance()->items;

    expect(array_column($items, 'kind'))->toBe(['dir', 'file']);
});

it('shows a not-configured state instead of the disk root when no storage exists', function () {
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, ['disk' => 'public', 'folders' => []]);
    Storage::disk('public')->makeDirectory('other-module');

    $component = Livewire::actingAs($this->user)->test('fm::file-manager')
        ->assertSee(__('fm::labels.not_configured'))
        ->assertDontSeeHtml('data-fm-item');

    expect($component->instance()->items)->toBe([]);
});

it('ignores id-based actions on files outside the current storage root', function () {
    $foreign = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/other', 'relative_path' => 'secret.pdf',
        'filename' => 'secret.pdf', 'original_name' => 'secret.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => false,
    ]);

    $component = Livewire::actingAs($this->user)->test('fm::file-manager')
        ->call('trash', $foreign->id)
        ->call('startRename', $foreign->id)
        ->assertNotDispatched('open-modal-fm-rename')
        ->set('selected', [$foreign->id])
        ->call('trashSelected')
        ->set('detailsId', $foreign->id);

    expect($foreign->fresh()->is_trashed)->toBeFalse()
        ->and($component->instance()->detailsFile)->toBeNull();

    $component->set('selected', [$foreign->id])->call('purgeSelected');
    expect(FmFile::find($foreign->id))->not->toBeNull();
});

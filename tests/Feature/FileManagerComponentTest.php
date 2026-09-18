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
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, [
        'disk' => 'public',
        'folders' => [
            ['path' => 'fm/public', 'name' => 'Public', 'permissions' => [
                'admin' => ['read', 'upload', 'delete', 'rename', 'move', 'copy', 'mkdir'],
            ]],
        ],
        'image' => ['thumbnail_width' => 200, 'thumbnail_height' => 200],
        'upload' => ['max_size_kb' => 10240, 'allowed_extensions' => ['jpg', 'png', 'pdf']],
        'trash' => ['auto_purge_days' => 30],
    ]);

    $devRole = Role::factory()->create(['name' => 'Developer', 'slug' => 'dev', 'level' => 0, 'can_access_backend' => true]);
    $this->user = User::factory()->create();
    $this->user->roles()->attach($devRole);

    Storage::disk('public')->put('fm/public/report.pdf', 'x');
    $this->file = FmFile::create([
        'disk' => 'public',
        'folder_path' => 'fm/public',
        'relative_path' => 'report.pdf',
        'filename' => 'report.pdf',
        'original_name' => 'report.pdf',
        'extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size' => 1,
        'has_thumbnail' => false,
        'is_trashed' => false,
    ]);
});

afterEach(function () {
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, null);
});

it('renders synapse modals and lightbox instead of hand-rolled overlays', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->assertOk()
        ->assertSeeHtml('open-modal-fm-rename')
        ->assertSeeHtml('open-modal-fm-new-folder')
        ->assertSeeHtml('open-modal-fm-move')
        ->assertSeeHtml('open-lightbox-fm-preview')
        ->assertDontSeeHtml('renameOpen')
        ->assertDontSeeHtml('previewOpen');
});

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

it('closes the new-folder modal after creating a folder', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->set('newFolderName', 'docs')
        ->call('createFolder')
        ->assertDispatched('close-modal-fm-new-folder');

    Storage::disk('public')->assertExists('fm/public/docs');
});

it('closes the move modal after moving files', function () {
    Livewire::actingAs($this->user)->test('fm::file-manager')
        ->set('selected', [$this->file->id])
        ->set('moveAction', 'move')
        ->set('moveTargetSubPath', 'archive')
        ->call('executeMoveOrCopy')
        ->assertDispatched('close-modal-fm-move');
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Services\FileManagerService;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    // Pin FmConfig to a known-good array so parallel test workers writing
    // fm.json (e.g. FmSetupCommandTest) can't corrupt the config mid-test.
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

    $this->service = app(FileManagerService::class);
});

it('uploads a file and creates a DB record', function () {
    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $fmFile = $this->service->upload($file, 'fm/public', '', null);

    expect($fmFile)->toBeInstanceOf(FmFile::class)
        ->and($fmFile->folder_path)->toBe('fm/public')
        ->and($fmFile->extension)->toBe('pdf')
        ->and($fmFile->is_trashed)->toBeFalse()
        ->and($fmFile->created_by)->toBeNull();

    Storage::disk('public')->assertExists('fm/public/'.$fmFile->filename);
});

it('uploads a file to a sub-path', function () {
    $file = UploadedFile::fake()->create('report.pdf', 50, 'application/pdf');

    $fmFile = $this->service->upload($file, 'fm/public', 'documents');

    expect($fmFile->relative_path)->toStartWith('documents/')
        ->and($fmFile->folder_path)->toBe('fm/public');

    Storage::disk('public')->assertExists('fm/public/'.$fmFile->relative_path);
});

it('moves a file to trash', function () {
    $fmFile = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'file.jpg',
        'filename' => 'file.jpg', 'original_name' => 'file.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 100, 'is_trashed' => false,
    ]);

    $this->service->delete($fmFile);

    expect($fmFile->fresh()->is_trashed)->toBeTrue()
        ->and($fmFile->fresh()->trashed_at)->not->toBeNull();
});

it('restores a trashed file', function () {
    $fmFile = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'file.jpg',
        'filename' => 'file.jpg', 'original_name' => 'file.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 100, 'is_trashed' => true, 'trashed_at' => now(),
    ]);

    $this->service->restore($fmFile);

    expect($fmFile->fresh()->is_trashed)->toBeFalse()
        ->and($fmFile->fresh()->trashed_at)->toBeNull();
});

it('purges a file from storage and DB', function () {
    Storage::disk('public')->put('fm/public/delete-me.pdf', 'content');

    $fmFile = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'delete-me.pdf',
        'filename' => 'delete-me.pdf', 'original_name' => 'delete-me.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 7, 'is_trashed' => true, 'trashed_at' => now(),
    ]);

    $id = $fmFile->id;
    $this->service->purge($fmFile);

    expect(FmFile::find($id))->toBeNull();
    Storage::disk('public')->assertMissing('fm/public/delete-me.pdf');
});

it('renames a file in storage and DB', function () {
    Storage::disk('public')->put('fm/public/old-name.pdf', 'content');

    $fmFile = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'old-name.pdf',
        'filename' => 'old-name.pdf', 'original_name' => 'old-name.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 7, 'is_trashed' => false,
    ]);

    $renamed = $this->service->rename($fmFile, 'new-name.pdf');

    expect($renamed->filename)->toBe('new-name.pdf')
        ->and($renamed->relative_path)->toBe('new-name.pdf');

    Storage::disk('public')->assertExists('fm/public/new-name.pdf');
    Storage::disk('public')->assertMissing('fm/public/old-name.pdf');
});

it('creates a folder in storage', function () {
    $this->service->createFolder('fm/public', '', 'my-folder');

    expect(Storage::disk('public')->directoryExists('fm/public/my-folder'))->toBeTrue();
});

it('creates a nested folder in storage', function () {
    Storage::disk('public')->makeDirectory('fm/public/parent');

    $this->service->createFolder('fm/public', 'parent', 'child');

    expect(Storage::disk('public')->directoryExists('fm/public/parent/child'))->toBeTrue();
});

it('lists directory contents', function () {
    Storage::disk('public')->makeDirectory('fm/public');
    Storage::disk('public')->makeDirectory('fm/public/subfolder');
    Storage::disk('public')->put('fm/public/file.jpg', 'content');

    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'file.jpg',
        'filename' => 'file.jpg', 'original_name' => 'file.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 7, 'is_trashed' => false,
    ]);

    $result = $this->service->listDirectory('fm/public', '');

    expect($result)->toHaveKey('dirs')
        ->and($result)->toHaveKey('files')
        ->and($result['dirs'])->toContain('subfolder')
        ->and($result['files'])->toHaveCount(1);
});

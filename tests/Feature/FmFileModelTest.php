<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use VmEngine\Fm\Models\FmFile;

uses(RefreshDatabase::class);

it('can create an fm_file record', function () {
    $file = FmFile::create([
        'disk' => 'public',
        'folder_path' => 'fm/public',
        'relative_path' => 'test-image.jpg',
        'filename' => 'test-image.jpg',
        'original_name' => 'Test Image.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size' => 102400,
        'has_thumbnail' => false,
        'is_trashed' => false,
    ]);

    expect($file->id)->toBeInt()
        ->and($file->filename)->toBe('test-image.jpg')
        ->and($file->is_trashed)->toBeFalse();
});

it('returns correct storage path', function () {
    $file = new FmFile([
        'folder_path' => 'fm/public',
        'relative_path' => 'documents/report.pdf',
    ]);

    expect($file->getStoragePath())->toBe('fm/public/documents/report.pdf');
});

it('returns correct thumbnail path for image at root', function () {
    $file = new FmFile([
        'folder_path' => 'fm/public',
        'relative_path' => 'photo.jpg',
        'filename' => 'photo.jpg',
        'extension' => 'jpg',
    ]);

    expect($file->getThumbnailPath())->toBe('fm/public/photo_thumb.jpg');
});

it('returns correct thumbnail path for image in subdirectory', function () {
    $file = new FmFile([
        'folder_path' => 'fm/public',
        'relative_path' => 'gallery/photo.png',
        'filename' => 'photo.png',
        'extension' => 'png',
    ]);

    expect($file->getThumbnailPath())->toBe('fm/public/gallery/photo_thumb.png');
});

it('identifies images correctly', function () {
    $image = new FmFile(['mime_type' => 'image/jpeg']);
    $pdf = new FmFile(['mime_type' => 'application/pdf']);

    expect($image->isImage())->toBeTrue()
        ->and($pdf->isImage())->toBeFalse();
});

it('returns human-readable size', function () {
    $file = new FmFile(['size' => 1024]);
    expect($file->getHumanSize())->toBe('1 KB');

    $file2 = new FmFile(['size' => 1048576]);
    expect($file2->getHumanSize())->toBe('1 MB');

    $file3 = new FmFile(['size' => 512]);
    expect($file3->getHumanSize())->toBe('512 B');
});

it('scopes active files correctly', function () {
    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'active.jpg',
        'filename' => 'active.jpg', 'original_name' => 'active.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 100, 'is_trashed' => false,
    ]);

    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'trashed.jpg',
        'filename' => 'trashed.jpg', 'original_name' => 'trashed.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 100, 'is_trashed' => true,
        'trashed_at' => now(),
    ]);

    $active = FmFile::active()->get();
    $trashed = FmFile::trashed()->get();

    expect($active)->toHaveCount(1)
        ->and($active->first()->filename)->toBe('active.jpg')
        ->and($trashed)->toHaveCount(1)
        ->and($trashed->first()->filename)->toBe('trashed.jpg');
});

it('scopes files in path correctly at root', function () {
    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'root-file.jpg',
        'filename' => 'root-file.jpg', 'original_name' => 'root-file.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 100, 'is_trashed' => false,
    ]);

    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'subdir/nested.jpg',
        'filename' => 'nested.jpg', 'original_name' => 'nested.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 100, 'is_trashed' => false,
    ]);

    $root = FmFile::inPath('fm/public', '')->get();

    expect($root)->toHaveCount(1)
        ->and($root->first()->filename)->toBe('root-file.jpg');
});

it('scopes files in specific sub-path', function () {
    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'root-file.jpg',
        'filename' => 'root-file.jpg', 'original_name' => 'root-file.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 100, 'is_trashed' => false,
    ]);

    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'docs/report.pdf',
        'filename' => 'report.pdf', 'original_name' => 'report.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 200, 'is_trashed' => false,
    ]);

    FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'docs/sub/deep.pdf',
        'filename' => 'deep.pdf', 'original_name' => 'deep.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 200, 'is_trashed' => false,
    ]);

    $docs = FmFile::inPath('fm/public', 'docs')->get();

    expect($docs)->toHaveCount(1)
        ->and($docs->first()->filename)->toBe('report.pdf');
});

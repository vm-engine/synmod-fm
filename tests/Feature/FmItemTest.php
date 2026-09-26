<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Support\FmItem;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('public'));

it('builds a folder item keyed by its sub-path', function () {
    expect(FmItem::dir('marketing', 'banners'))->toMatchArray([
        'kind' => 'dir',
        'key' => 'marketing/banners',
        'name' => 'banners',
        'icon' => 'ph-folder',
        'tone' => 'folder',
        'thumb' => null,
    ])->and(FmItem::dir('', 'docs')['key'])->toBe('docs');
});

it('builds a file item with tone, size, location and preview kind', function () {
    $file = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'docs/brief.pdf',
        'filename' => 'brief.pdf', 'original_name' => 'brief.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 2048, 'is_trashed' => false,
    ]);

    expect(FmItem::file($file))->toMatchArray([
        'kind' => 'file',
        'key' => (string) $file->id,
        'name' => 'brief.pdf',
        'size' => '2 KB',
        'location' => 'docs',
        'icon' => 'ph-file-pdf',
        'tone' => 'pdf',
        'preview' => '',
        'ext' => 'PDF',
        'thumb' => null,
    ]);
});

it('uses the thumbnail url and image preview for images', function () {
    $file = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'hero.jpg',
        'filename' => 'hero.jpg', 'original_name' => 'hero.jpg', 'extension' => 'jpg',
        'mime_type' => 'image/jpeg', 'size' => 10, 'has_thumbnail' => true, 'is_trashed' => false,
    ]);

    $item = FmItem::file($file);

    expect($item['preview'])->toBe('image')
        ->and($item['thumb'])->toBe($file->getThumbnailUrl());
});

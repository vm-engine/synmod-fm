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
        'preview' => 'pdf',
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

it('builds a nested folder item from a search result path', function () {
    expect(FmItem::dir('marketing', '2024/banners'))->toMatchArray([
        'key' => 'marketing/2024/banners',
        'name' => 'banners',
        'location' => 'marketing/2024',
    ])->and(FmItem::dir('', 'a/b')['location'])->toBe('a');
});

it('flags pdfs and videos without a thumbnail for browser rendering', function () {
    $pdf = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'brief.pdf',
        'filename' => 'brief.pdf', 'original_name' => 'brief.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => false, 'has_thumbnail' => false,
    ]);
    $video = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'clip.mp4',
        'filename' => 'clip.mp4', 'original_name' => 'clip.mp4', 'extension' => 'mp4',
        'mime_type' => 'video/mp4', 'size' => 1, 'is_trashed' => false, 'has_thumbnail' => false,
    ]);

    expect(FmItem::file($pdf))->toMatchArray(['thumb' => null, 'thumbKind' => 'pdf'])
        ->and(FmItem::file($video))->toMatchArray(['thumb' => null, 'thumbKind' => 'video'])
        ->and(FmItem::dir('', 'docs')['thumbKind'])->toBe('');
});

it('uses the stored thumbnail once a pdf has one', function () {
    $pdf = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'brief.pdf',
        'filename' => 'brief.pdf', 'original_name' => 'brief.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => false, 'has_thumbnail' => true,
    ]);

    $item = FmItem::file($pdf);

    expect($item['thumbKind'])->toBe('')
        ->and($item['thumb'])->toEndWith('/fm/public/brief.pdf_thumb.jpg');
});

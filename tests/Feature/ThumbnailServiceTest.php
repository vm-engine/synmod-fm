<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Services\ThumbnailService;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');

    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, [
        'disk' => 'public',
        'folders' => [['path' => 'fm/public', 'name' => 'Public', 'permissions' => []]],
        'image' => ['thumbnail_width' => 200, 'thumbnail_height' => 200],
        'upload' => ['max_size_kb' => 10240, 'allowed_extensions' => ['pdf']],
        'trash' => ['auto_purge_days' => 30],
    ]);

    $this->makeFile = fn (string $name, string $mime, bool $hasThumb = false): FmFile => FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => $name,
        'filename' => $name, 'original_name' => $name, 'extension' => pathinfo($name, PATHINFO_EXTENSION),
        'mime_type' => $mime, 'size' => 1, 'is_trashed' => false, 'has_thumbnail' => $hasThumb,
    ]);

    // Test fixture only — GD is a dev dependency here, never needed at runtime.
    $this->jpegDataUrl = function (int $width = 100, int $height = 140, string $type = 'jpg'): string {
        $fake = UploadedFile::fake()->image('t.'.$type, $width, $height);
        $mime = $type === 'png' ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($fake->getPathname()));
    };
});

afterEach(function () {
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, null);
});

it('stores a browser-rendered jpeg for a pdf', function () {
    $pdf = ($this->makeFile)('brief.pdf', 'application/pdf');

    expect(app(ThumbnailService::class)->storeClientThumbnail($pdf, ($this->jpegDataUrl)()))->toBeTrue();

    Storage::disk('public')->assertExists('fm/public/brief_thumb.jpg');
    expect($pdf->fresh()->has_thumbnail)->toBeTrue();
});

it('stores a browser-rendered jpeg for a video', function () {
    $video = ($this->makeFile)('clip.mp4', 'video/mp4');

    expect(app(ThumbnailService::class)->storeClientThumbnail($video, ($this->jpegDataUrl)(400, 225)))->toBeTrue();

    Storage::disk('public')->assertExists('fm/public/clip_thumb.jpg');
});

it('rejects client thumbnails it should not store', function (string $case) {
    $service = app(ThumbnailService::class);

    [$file, $dataUrl] = match ($case) {
        'image file' => [($this->makeFile)('logo.png', 'image/png'), ($this->jpegDataUrl)()],
        'already has one' => [($this->makeFile)('brief.pdf', 'application/pdf', true), ($this->jpegDataUrl)()],
        'png payload' => [($this->makeFile)('brief.pdf', 'application/pdf'), ($this->jpegDataUrl)(100, 100, 'png')],
        'mislabelled png' => [($this->makeFile)('brief.pdf', 'application/pdf'), str_replace('image/png', 'image/jpeg', ($this->jpegDataUrl)(100, 100, 'png'))],
        'not base64' => [($this->makeFile)('brief.pdf', 'application/pdf'), 'data:image/jpeg;base64,@@@not-base64@@@'],
        'garbage bytes' => [($this->makeFile)('brief.pdf', 'application/pdf'), 'data:image/jpeg;base64,'.base64_encode('hello')],
        'too many pixels' => [($this->makeFile)('brief.pdf', 'application/pdf'), ($this->jpegDataUrl)(401, 100)],
        'too many bytes' => [($this->makeFile)('brief.pdf', 'application/pdf'), 'data:image/jpeg;base64,'.str_repeat('A', 500 * 1024)],
    };

    expect($service->storeClientThumbnail($file, $dataUrl))->toBeFalse()
        ->and(Storage::disk('public')->allFiles('fm/public'))->toBe([]);
})->with(['image file', 'already has one', 'png payload', 'mislabelled png', 'not base64', 'garbage bytes', 'too many pixels', 'too many bytes']);

it('never overwrites an existing file at the thumbnail path', function () {
    Storage::disk('public')->put('fm/public/brief_thumb.jpg', 'a real upload, not a thumbnail');
    $pdf = ($this->makeFile)('brief.pdf', 'application/pdf');

    expect(app(ThumbnailService::class)->storeClientThumbnail($pdf, ($this->jpegDataUrl)()))->toBeFalse()
        ->and(Storage::disk('public')->get('fm/public/brief_thumb.jpg'))->toBe('a real upload, not a thumbnail')
        ->and($pdf->fresh()->has_thumbnail)->toBeFalse();
});

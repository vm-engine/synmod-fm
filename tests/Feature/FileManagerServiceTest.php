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

it('removes the thumbnail when purging a file', function (string $name, string $mime, string $thumb) {
    Storage::disk('public')->put('fm/public/docs/'.$name, 'content');
    Storage::disk('public')->put('fm/public/docs/'.$thumb, 'thumb');

    $fmFile = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'docs/'.$name,
        'filename' => $name, 'original_name' => $name, 'extension' => pathinfo($name, PATHINFO_EXTENSION),
        'mime_type' => $mime, 'size' => 7, 'has_thumbnail' => true, 'is_trashed' => true, 'trashed_at' => now(),
    ]);

    $this->service->purge($fmFile);

    Storage::disk('public')->assertMissing('fm/public/docs/'.$name);
    Storage::disk('public')->assertMissing('fm/public/docs/'.$thumb);
})->with([
    'image (server thumbnail)' => ['photo.png', 'image/png', 'photo_thumb.png'],
    'pdf (browser thumbnail)' => ['brief.pdf', 'application/pdf', 'brief.pdf_thumb.jpg'],
    'video (browser thumbnail)' => ['clip.mp4', 'video/mp4', 'clip.mp4_thumb.jpg'],
]);

it('keeps a same-named user file when the purged file has no thumbnail', function () {
    Storage::disk('public')->put('fm/public/brief.pdf', 'content');
    Storage::disk('public')->put('fm/public/brief.pdf_thumb.jpg', 'a real upload, not a thumbnail');

    $fmFile = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => 'brief.pdf',
        'filename' => 'brief.pdf', 'original_name' => 'brief.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 7, 'has_thumbnail' => false, 'is_trashed' => true, 'trashed_at' => now(),
    ]);

    $this->service->purge($fmFile);

    Storage::disk('public')->assertExists('fm/public/brief.pdf_thumb.jpg');
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

it('lists every trashed file of a storage root regardless of sub-path', function () {
    $make = fn (string $path, bool $trashed) => FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => $path,
        'filename' => basename($path), 'original_name' => basename($path), 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => $trashed,
        'trashed_at' => $trashed ? now() : null,
    ]);

    $make('root.pdf', true);
    $make('docs/deep/old.pdf', true);
    $make('docs/live.pdf', false);

    $names = $this->service->listTrash('fm/public')->pluck('filename')->all();

    expect($names)->toBe(['old.pdf', 'root.pdf']);
});

function makeSearchFile(string $path): FmFile
{
    Storage::disk('public')->put('fm/public/'.$path, 'x');

    return FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => $path,
        'filename' => basename($path), 'original_name' => basename($path), 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => false,
    ]);
}

it('searches files and folders in the current folder and below', function () {
    makeSearchFile('report-root.pdf');
    makeSearchFile('docs/2024/report-q1.pdf');
    makeSearchFile('docs/other.pdf');
    Storage::disk('public')->makeDirectory('fm/public/docs/reports');
    Storage::disk('public')->makeDirectory('fm/public/misc');

    $result = $this->service->listDirectory('fm/public', '', false, 'report');

    expect($result['dirs'])->toBe(['docs/reports'])
        ->and($result['files']->pluck('filename')->sort()->values()->all())->toBe(['report-q1.pdf', 'report-root.pdf'])
        ->and($result['truncated'])->toBeFalse();
});

it('does not search outside the current sub-path', function () {
    makeSearchFile('report-root.pdf');
    makeSearchFile('docs/2024/report-q1.pdf');
    Storage::disk('public')->makeDirectory('fm/public/archive/reports');

    $result = $this->service->listDirectory('fm/public', 'docs', false, 'REPORT');

    expect($result['dirs'])->toBe([])
        ->and($result['files']->pluck('filename')->all())->toBe(['report-q1.pdf']);
});

it('caps search results and flags truncation', function () {
    foreach (range(1, FileManagerService::SEARCH_LIMIT - 1) as $i) {
        makeSearchFile("a/hit-{$i}.pdf");
    }
    Storage::disk('public')->makeDirectory('fm/public/b/hit-dir-1');
    Storage::disk('public')->makeDirectory('fm/public/b/hit-dir-2');

    $result = $this->service->listDirectory('fm/public', '', false, 'hit');

    expect($result['dirs'])->toHaveCount(2)
        ->and($result['files'])->toHaveCount(FileManagerService::SEARCH_LIMIT - 2)
        ->and($result['truncated'])->toBeTrue();
});

it('lists only one level and never truncates without a search', function () {
    makeSearchFile('top.pdf');
    makeSearchFile('docs/nested.pdf');

    $result = $this->service->listDirectory('fm/public', '');

    expect($result['dirs'])->toBe(['docs'])
        ->and($result['files']->pluck('filename')->all())->toBe(['top.pdf'])
        ->and($result['truncated'])->toBeFalse();
});

function makeZipSource(string $relativePath, bool $trashed = false): FmFile
{
    Storage::disk('public')->put('fm/public/'.$relativePath, 'content of '.$relativePath);

    return FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/public', 'relative_path' => $relativePath,
        'filename' => basename($relativePath), 'original_name' => basename($relativePath),
        'extension' => pathinfo($relativePath, PATHINFO_EXTENSION), 'mime_type' => 'application/pdf',
        'size' => 10, 'is_trashed' => $trashed, 'trashed_at' => $trashed ? now() : null,
    ]);
}

/** @return array<int, string> */
function zipEntries(FmFile $zip): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'fmtest');
    file_put_contents($tmp, Storage::disk('public')->get($zip->getStoragePath()));
    $archive = new ZipArchive;
    $archive->open($tmp);
    $names = [];
    for ($i = 0; $i < $archive->numFiles; $i++) {
        $names[] = $archive->getNameIndex($i);
    }
    $archive->close();
    unlink($tmp);
    sort($names);

    return $names;
}

it('compresses selected files and folder contents into a zip', function () {
    $a = makeZipSource('docs/a.pdf');
    makeZipSource('docs/photos/b.pdf');
    makeZipSource('docs/photos/sub/c.pdf');
    makeZipSource('docs/photos/old.pdf', trashed: true);
    makeZipSource('docs/photos_other/x.pdf'); // sibling with shared prefix

    $zip = $this->service->compress([$a->id], ['docs/photos'], 'fm/public', 'docs', null);

    expect($zip->filename)->toBe('archive.zip')
        ->and($zip->relative_path)->toBe('docs/archive.zip')
        ->and($zip->extension)->toBe('zip')
        ->and($zip->mime_type)->toBe('application/zip')
        ->and($zip->size)->toBeGreaterThan(0)
        ->and(zipEntries($zip))->toBe(['a.pdf', 'photos/b.pdf', 'photos/sub/c.pdf']);
});

it('names a single-item zip after that item and avoids collisions', function () {
    $a = makeZipSource('report.pdf');
    Storage::disk('public')->put('fm/public/report.zip', 'existing');

    expect($this->service->compress([$a->id], [], 'fm/public', '', null)->filename)->toBe('report_1.zip')
        ->and($this->service->compress([], ['docs'], 'fm/public', '', null))->toBeNull();

    makeZipSource('docs/x.pdf');

    expect($this->service->compress([], ['docs'], 'fm/public', '', null)->filename)->toBe('docs.zip');
});

it('ignores files of another storage root and trashed files when compressing', function () {
    $trashed = makeZipSource('gone.pdf', trashed: true);
    $foreign = FmFile::create([
        'disk' => 'public', 'folder_path' => 'fm/private', 'relative_path' => 'secret.pdf',
        'filename' => 'secret.pdf', 'original_name' => 'secret.pdf', 'extension' => 'pdf',
        'mime_type' => 'application/pdf', 'size' => 1, 'is_trashed' => false,
    ]);

    expect($this->service->compress([$trashed->id, $foreign->id], [], 'fm/public', '', null))->toBeNull();
});

it('returns null when none of the files to compress exist on disk', function () {
    $ghost = makeZipSource('ghost.pdf');
    Storage::disk('public')->delete('fm/public/ghost.pdf');

    expect($this->service->compress([$ghost->id], [], 'fm/public', '', null))->toBeNull()
        ->and(FmFile::where('extension', 'zip')->exists())->toBeFalse();
});

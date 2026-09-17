<?php

declare(strict_types=1);

namespace VmEngine\Fm\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Models\FmFile;
use VmEngine\SynAuth\Facades\SynAuth;

class FileManagerService
{
    public function __construct(
        private readonly ThumbnailService $thumbnailService
    ) {}

    /**
     * List contents of a directory: subdirectories and files.
     *
     * @return array{dirs: array<string>, files: Collection<int, FmFile>}
     */
    public function listDirectory(string $folderPath, string $subPath = '', bool $showTrash = false, string $search = '', string $sortBy = 'filename', string $sortDir = 'asc'): array
    {
        $disk = Storage::disk(FmConfig::getDisk());
        $fullPath = $subPath !== ''
            ? $folderPath.'/'.$subPath
            : $folderPath;

        // Ensure the directory exists
        if (! $disk->directoryExists($fullPath)) {
            $disk->makeDirectory($fullPath);
        }

        // Get subdirectories from storage
        $storageDirs = $disk->directories($fullPath);
        $dirs = array_map(fn ($d) => basename($d), $storageDirs);
        sort($dirs);

        // Get files from DB
        $query = FmFile::inPath($folderPath, $subPath);

        if ($showTrash) {
            $query->trashed();
        } else {
            $query->active();
        }

        if ($search !== '') {
            $query->search($search);
        }

        $allowed = ['filename', 'size', 'extension', 'created_at'];
        $col = in_array($sortBy, $allowed, true) ? $sortBy : 'filename';
        $dir = $sortDir === 'desc' ? 'desc' : 'asc';

        $files = $query->orderBy($col, $dir)->get();

        return ['dirs' => $dirs, 'files' => $files];
    }

    /**
     * Upload a file to the given folder and sub-path.
     */
    public function upload(UploadedFile $file, string $folderPath, string $subPath = '', ?int $createdBy = null): FmFile
    {
        $disk = FmConfig::getDisk();
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $this->uniqueFilename($folderPath, $subPath, $file->getClientOriginalName());
        $relativePath = $subPath !== '' ? $subPath.'/'.$filename : $filename;
        $storagePath = $folderPath.'/'.$relativePath;

        $stored = Storage::disk($disk)->putFileAs(
            $folderPath.($subPath !== '' ? '/'.$subPath : ''),
            $file,
            $filename
        );

        if ($stored === false) {
            throw new \RuntimeException("Failed to write file '{$filename}' to disk '{$disk}' at path '{$folderPath}'.");
        }

        $fmFile = FmFile::create([
            'disk' => $disk,
            'folder_path' => $folderPath,
            'relative_path' => $relativePath,
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
            'extension' => $extension,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize(),
            'has_thumbnail' => false,
            'is_trashed' => false,
            'created_by' => $createdBy,
        ]);

        // Generate thumbnail for images
        if ($fmFile->isImage()) {
            $this->thumbnailService->generate($fmFile);
        }

        $this->log(
            $createdBy ?? Auth::id(),
            'fm.file.upload',
            'Uploaded '.$fmFile->filename.' to '.$fmFile->getStoragePath().' ('.$fmFile->size.' bytes)',
            'file'
        );

        return $fmFile;
    }

    /**
     * Move a file to trash (soft delete).
     */
    public function delete(FmFile $file): void
    {
        $file->update([
            'is_trashed' => true,
            'trashed_at' => now(),
        ]);

        $this->log(
            Auth::id(),
            'fm.file.trash',
            'Moved '.$file->filename.' to trash ('.$file->getStoragePath().')',
            'file'
        );
    }

    /**
     * Restore a trashed file.
     */
    public function restore(FmFile $file): void
    {
        $file->update([
            'is_trashed' => false,
            'trashed_at' => null,
        ]);

        $this->log(
            Auth::id(),
            'fm.file.restore',
            'Restored '.$file->filename.' from trash ('.$file->getStoragePath().')',
            'file'
        );
    }

    /**
     * Permanently delete a file from storage and DB.
     */
    public function purge(FmFile $file): void
    {
        $disk = Storage::disk($file->disk);
        $storagePath = $file->getStoragePath();
        $purgedName = $file->filename;
        $purgedPath = $storagePath;

        if ($disk->exists($storagePath)) {
            $disk->delete($storagePath);
        }

        if ($file->has_thumbnail) {
            $thumbPath = $file->getThumbnailPath();
            if ($disk->exists($thumbPath)) {
                $disk->delete($thumbPath);
            }
        }

        $file->delete();

        $this->log(
            Auth::id(),
            'fm.file.purge',
            'Permanently deleted '.$purgedName.' ('.$purgedPath.')',
            'file'
        );
    }

    /**
     * Rename a file in storage and update the DB record.
     */
    public function rename(FmFile $file, string $newName): FmFile
    {
        $oldName = $file->filename;
        $newName = $this->sanitizeFilename($newName);
        $extension = pathinfo($newName, PATHINFO_EXTENSION);
        if ($extension === '') {
            $newName .= '.'.$file->extension;
        }

        $dir = pathinfo($file->relative_path, PATHINFO_DIRNAME);
        $newRelativePath = $dir !== '.' ? $dir.'/'.$newName : $newName;
        $oldStoragePath = $file->getStoragePath();
        $newStoragePath = $file->folder_path.'/'.$newRelativePath;

        $disk = Storage::disk($file->disk);

        if ($disk->exists($oldStoragePath)) {
            $disk->move($oldStoragePath, $newStoragePath);
        }

        // Move thumbnail if it exists
        if ($file->has_thumbnail) {
            $oldThumb = $file->getThumbnailPath();
            $file->filename = $newName;
            $file->relative_path = $newRelativePath;
            $newThumb = $file->getThumbnailPath();
            if ($disk->exists($oldThumb)) {
                $disk->move($oldThumb, $newThumb);
            }
        }

        $file->update([
            'filename' => $newName,
            'relative_path' => $newRelativePath,
            'extension' => strtolower(pathinfo($newName, PATHINFO_EXTENSION) ?: $file->extension),
        ]);

        $this->log(
            Auth::id(),
            'fm.file.rename',
            'Renamed '.$oldName.' → '.$newName.' in '.$file->folder_path,
            'file'
        );

        return $file->fresh();
    }

    /**
     * Move files to a new location (folder + sub-path).
     *
     * @param  array<int>  $fileIds
     */
    public function move(array $fileIds, string $targetFolderPath, string $targetSubPath = ''): void
    {
        $files = FmFile::whereIn('id', $fileIds)->get();
        $disk = Storage::disk(FmConfig::getDisk());

        $movedNames = [];

        foreach ($files as $file) {
            $newFilename = $this->uniqueFilename($targetFolderPath, $targetSubPath, $file->filename);
            $newRelativePath = $targetSubPath !== '' ? $targetSubPath.'/'.$newFilename : $newFilename;
            $newStoragePath = $targetFolderPath.'/'.$newRelativePath;

            if ($disk->exists($file->getStoragePath())) {
                $disk->move($file->getStoragePath(), $newStoragePath);
            }

            if ($file->has_thumbnail) {
                $oldThumbPath = $file->getThumbnailPath();
                $file->filename = $newFilename;
                $file->relative_path = $newRelativePath;
                $newThumbPath = $file->getThumbnailPath();
                if ($disk->exists($oldThumbPath)) {
                    $disk->move($oldThumbPath, $newThumbPath);
                }
            }

            $file->update([
                'disk' => FmConfig::getDisk(),
                'folder_path' => $targetFolderPath,
                'relative_path' => $newRelativePath,
                'filename' => $newFilename,
            ]);

            $movedNames[] = $newFilename;
        }

        if ($movedNames !== []) {
            $target = $targetSubPath !== '' ? $targetFolderPath.'/'.$targetSubPath : $targetFolderPath;
            $this->log(
                Auth::id(),
                'fm.file.move',
                'Moved '.count($movedNames).' file(s) to '.$target.': '.implode(', ', $movedNames),
                'file'
            );
        }
    }

    /**
     * Copy files to a new location.
     *
     * @param  array<int>  $fileIds
     */
    public function copy(array $fileIds, string $targetFolderPath, string $targetSubPath = ''): void
    {
        $files = FmFile::whereIn('id', $fileIds)->get();
        $disk = Storage::disk(FmConfig::getDisk());

        $copiedNames = [];

        foreach ($files as $file) {
            $newFilename = $this->uniqueFilename($targetFolderPath, $targetSubPath, $file->filename);
            $newRelativePath = $targetSubPath !== '' ? $targetSubPath.'/'.$newFilename : $newFilename;
            $newStoragePath = $targetFolderPath.'/'.$newRelativePath;

            if ($disk->exists($file->getStoragePath())) {
                $disk->copy($file->getStoragePath(), $newStoragePath);
            }

            $newFile = $file->replicate();
            $newFile->disk = FmConfig::getDisk();
            $newFile->folder_path = $targetFolderPath;
            $newFile->relative_path = $newRelativePath;
            $newFile->filename = $newFilename;
            $newFile->has_thumbnail = false;
            $newFile->save();

            // Regenerate thumbnail for the copy
            if ($newFile->isImage()) {
                $this->thumbnailService->generate($newFile);
            }

            $copiedNames[] = $newFilename;
        }

        if ($copiedNames !== []) {
            $target = $targetSubPath !== '' ? $targetFolderPath.'/'.$targetSubPath : $targetFolderPath;
            $this->log(
                Auth::id(),
                'fm.file.copy',
                'Copied '.count($copiedNames).' file(s) to '.$target.': '.implode(', ', $copiedNames),
                'file'
            );
        }
    }

    /**
     * Create a new folder in storage.
     */
    public function createFolder(string $parentFolderPath, string $subPath, string $name): void
    {
        $name = $this->sanitizeFilename($name);
        $fullPath = $subPath !== ''
            ? $parentFolderPath.'/'.$subPath.'/'.$name
            : $parentFolderPath.'/'.$name;

        Storage::disk(FmConfig::getDisk())->makeDirectory($fullPath);

        $this->log(
            Auth::id(),
            'fm.folder.create',
            'Created folder '.$fullPath,
            'folder'
        );
    }

    /**
     * Get the public URL for a file.
     */
    public function getUrl(FmFile $file): string
    {
        return $file->getUrl();
    }

    /**
     * Purge all files trashed before the configured auto-purge threshold.
     */
    public function purgeExpiredTrash(): int
    {
        $days = (int) (FmConfig::getTrashConfig()['auto_purge_days'] ?? 30);
        $cutoff = now()->subDays($days);

        $files = FmFile::trashed()->where('trashed_at', '<', $cutoff)->get();
        $count = 0;

        foreach ($files as $file) {
            $this->purge($file);
            $count++;
        }

        return $count;
    }

    /**
     * Generate a unique filename in the given folder/sub-path.
     */
    private function uniqueFilename(string $folderPath, string $subPath, string $originalName): string
    {
        $disk = Storage::disk(FmConfig::getDisk());
        $filename = $this->sanitizeFilename($originalName);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $counter = 0;

        while (true) {
            $candidate = $counter === 0 ? $filename : $base.'_'.$counter.($ext ? '.'.$ext : '');
            $path = $subPath !== ''
                ? $folderPath.'/'.$subPath.'/'.$candidate
                : $folderPath.'/'.$candidate;

            if (! $disk->exists($path)) {
                return $candidate;
            }

            $counter++;
        }
    }

    /**
     * Sanitize a filename by removing unsafe characters.
     */
    private function sanitizeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^\w\s.\-]/', '', $name) ?? $name;
        $name = preg_replace('/\s+/', '_', $name) ?? $name;

        return ltrim($name, '.');
    }

    /**
     * Write a file-manager activity entry to the auth activity log.
     *
     * Skipped when no user context is available (e.g. unauthenticated API
     * calls or the scheduled trash-purge command), since the
     * `user_activities` table requires a non-null user_id.
     */
    private function log(int|string|null $userId, string $action, string $description, string $feature): void
    {
        if ($userId === null) {
            return;
        }

        SynAuth::logActivity((int) $userId, $action, $description, $feature, 'fm');
    }
}

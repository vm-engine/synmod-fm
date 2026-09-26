<?php

declare(strict_types=1);

namespace VmEngine\Fm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use VmEngine\Synapse\Traits\WithDeleteToken;

/**
 * @property int $id
 * @property string $disk
 * @property string $folder_path
 * @property string $relative_path
 * @property string $filename
 * @property string $original_name
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property bool $has_thumbnail
 * @property bool $is_trashed
 * @property Carbon|null $trashed_at
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FmFile extends Model
{
    use WithDeleteToken;

    protected $table = 'fm_files';

    protected $fillable = [
        'disk',
        'folder_path',
        'relative_path',
        'filename',
        'original_name',
        'extension',
        'mime_type',
        'size',
        'has_thumbnail',
        'is_trashed',
        'trashed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'has_thumbnail' => 'boolean',
            'is_trashed' => 'boolean',
            'trashed_at' => 'datetime',
        ];
    }

    /**
     * Get the creator user.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the full storage path (disk-relative).
     */
    public function getStoragePath(): string
    {
        return $this->folder_path.'/'.$this->relative_path;
    }

    /**
     * Get the thumbnail storage path (disk-relative).
     */
    public function getThumbnailPath(): string
    {
        $ext = $this->extension;
        $base = pathinfo($this->relative_path, PATHINFO_FILENAME);
        $dir = pathinfo($this->relative_path, PATHINFO_DIRNAME);
        $thumbName = $base.'_thumb.'.$ext;

        return $dir !== '.'
            ? $this->folder_path.'/'.$dir.'/'.$thumbName
            : $this->folder_path.'/'.$thumbName;
    }

    /**
     * Get the public URL of the file (path-only, host-agnostic).
     */
    public function getUrl(): string
    {
        $url = Storage::disk($this->disk)->url($this->getStoragePath());
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? $path : $url;
    }

    /**
     * Get the public URL of the thumbnail (falls back to file URL if no thumb).
     */
    public function getThumbnailUrl(): string
    {
        if ($this->has_thumbnail) {
            $url = Storage::disk($this->disk)->url($this->getThumbnailPath());
            $path = parse_url($url, PHP_URL_PATH);

            return is_string($path) ? $path : $url;
        }

        return $this->getUrl();
    }

    /**
     * Check if this file is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if this file is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Get a human-readable file size.
     */
    public function getHumanSize(): string
    {
        return self::formatBytes((int) $this->size);
    }

    /**
     * Format a byte count as B / KB / MB (one decimal, trailing .0 dropped).
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }

    /**
     * "W×H" when width/height attributes are present, otherwise null.
     *
     * The fm_files table has no width/height columns yet — reading the raw
     * attribute array (not $this->width) keeps this safe under
     * Model::preventAccessingMissingAttributes() and lights up automatically
     * once the columns are added.
     */
    public function dimensions(): ?string
    {
        $attributes = $this->getAttributes();
        $width = $attributes['width'] ?? null;
        $height = $attributes['height'] ?? null;

        if (! is_numeric($width) || ! is_numeric($height)) {
            return null;
        }

        return (int) $width.'×'.(int) $height;
    }

    /**
     * How the UI can preview this file: "image", "video", or null.
     */
    public function previewKind(): ?string
    {
        return match (true) {
            $this->isImage() => 'image',
            $this->isVideo() => 'video',
            default => null,
        };
    }

    /**
     * Scope: active (not trashed).
     *
     * @param  Builder<FmFile>  $query
     * @return Builder<FmFile>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_trashed', false);
    }

    /**
     * Scope: trashed only.
     *
     * @param  Builder<FmFile>  $query
     * @return Builder<FmFile>
     */
    public function scopeTrashed(Builder $query): Builder
    {
        return $query->where('is_trashed', true);
    }

    /**
     * Scope: files in a specific folder and sub-path.
     *
     * @param  Builder<FmFile>  $query
     * @return Builder<FmFile>
     */
    public function scopeInPath(Builder $query, string $folderPath, string $subPath = ''): Builder
    {
        $query->where('folder_path', $folderPath);

        if ($subPath === '') {
            $query->where('relative_path', 'not like', '%/%');
        } else {
            $normalised = rtrim($subPath, '/');
            $query->where('relative_path', 'like', $normalised.'/%')
                ->where('relative_path', 'not like', $normalised.'/%/%');
        }

        return $query;
    }

    /**
     * Scope: files in a sub-path and all of its descendants (whole root when empty).
     *
     * @param  Builder<FmFile>  $query
     * @return Builder<FmFile>
     */
    public function scopeUnderPath(Builder $query, string $folderPath, string $subPath = ''): Builder
    {
        $query->where('folder_path', $folderPath);

        if ($subPath !== '') {
            $query->where('relative_path', 'like', rtrim($subPath, '/').'/%');
        }

        return $query;
    }

    /**
     * Scope: search by filename or original name.
     *
     * @param  Builder<FmFile>  $query
     * @return Builder<FmFile>
     */
    public function scopeSearch(Builder $query, string $q): Builder
    {
        return $query->where(function (Builder $q2) use ($q) {
            $q2->where('filename', 'like', '%'.$q.'%')
                ->orWhere('original_name', 'like', '%'.$q.'%');
        });
    }
}

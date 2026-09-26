<?php

declare(strict_types=1);

namespace VmEngine\Fm\Services;

use Illuminate\Support\Facades\Storage;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Models\FmFile;

class ThumbnailService
{
    /**
     * Generate a thumbnail for the given file using GD.
     * Updates $file->has_thumbnail and saves the model.
     */
    public function generate(FmFile $file): void
    {
        if (! $file->isImage()) {
            return;
        }

        if (! extension_loaded('gd')) {
            return;
        }

        $disk = Storage::disk($file->disk);
        $storagePath = $file->getStoragePath();

        if (! $disk->exists($storagePath)) {
            return;
        }

        $imageConfig = FmConfig::getImageConfig();
        $maxW = (int) ($imageConfig['thumbnail_width'] ?? 200);
        $maxH = (int) ($imageConfig['thumbnail_height'] ?? 200);

        $contents = $disk->get($storagePath);
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);

        [$thumbW, $thumbH] = $this->calculateDimensions($srcW, $srcH, $maxW, $maxH);

        $thumbW = max(1, $thumbW);
        $thumbH = max(1, $thumbH);

        $thumb = imagecreatetruecolor($thumbW, $thumbH);

        // Preserve transparency for PNG/WebP
        if (in_array(strtolower($file->extension), ['png', 'webp'], true)) {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);

            if ($transparent === false) {
                imagedestroy($source);
                imagedestroy($thumb);

                return;
            }

            imagefilledrectangle($thumb, 0, 0, $thumbW, $thumbH, $transparent);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbW, $thumbH, $srcW, $srcH);

        ob_start();
        $ext = strtolower($file->extension);
        match ($ext) {
            'jpg', 'jpeg' => imagejpeg($thumb, null, 85),
            'png' => imagepng($thumb, null, 6),
            'gif' => imagegif($thumb),
            'webp' => imagewebp($thumb, null, 85),
            default => imagejpeg($thumb, null, 85),
        };
        $thumbContents = ob_get_clean();

        imagedestroy($source);
        imagedestroy($thumb);

        if ($thumbContents !== false && $thumbContents !== '') {
            $disk->put($file->getThumbnailPath(), $thumbContents);
            $file->has_thumbnail = true;
            $file->save();
        }
    }

    /** Upper bound for a decoded browser-rendered thumbnail. */
    private const CLIENT_MAX_BYTES = 300 * 1024;

    /**
     * Store a thumbnail the browser rendered for a PDF/video (fm.js).
     *
     * The payload is client-controlled, so it must be a JPEG data URL whose
     * decoded bytes really are a JPEG (core getimagesizefromstring — no GD or
     * Imagick) within a byte cap and 2× the configured thumbnail box (retina).
     * Files that already have a thumbnail are left alone.
     */
    public function storeClientThumbnail(FmFile $file, string $dataUrl): bool
    {
        if ($file->has_thumbnail || ! $file->canHaveClientThumbnail()) {
            return false;
        }

        $prefix = 'data:image/jpeg;base64,';

        // Base64 inflates by 4/3 — reject oversized payloads before decoding.
        if (! str_starts_with($dataUrl, $prefix) || strlen($dataUrl) > strlen($prefix) + (int) ceil(self::CLIENT_MAX_BYTES * 4 / 3) + 4) {
            return false;
        }

        $jpeg = base64_decode(substr($dataUrl, strlen($prefix)), true);

        if ($jpeg === false || $jpeg === '' || strlen($jpeg) > self::CLIENT_MAX_BYTES) {
            return false;
        }

        $info = @getimagesizefromstring($jpeg);
        ['width' => $maxW, 'height' => $maxH] = self::clientThumbnailBox();

        if ($info === false || $info[2] !== IMAGETYPE_JPEG || $info[0] > $maxW || $info[1] > $maxH) {
            return false;
        }

        $disk = Storage::disk($file->disk);
        $thumbPath = $file->getThumbnailPath();

        // A file already there is a user upload (e.g. "brief_thumb.jpg"), not ours — never overwrite it.
        if ($disk->exists($thumbPath)) {
            return false;
        }

        $disk->put($thumbPath, $jpeg);
        $file->has_thumbnail = true;
        $file->save();

        return true;
    }

    /**
     * Largest browser-rendered thumbnail accepted: 2× the configured box (retina).
     *
     * @return array{width: int, height: int}
     */
    public static function clientThumbnailBox(): array
    {
        $imageConfig = FmConfig::getImageConfig();

        return [
            'width' => 2 * (int) ($imageConfig['thumbnail_width'] ?? 200),
            'height' => 2 * (int) ($imageConfig['thumbnail_height'] ?? 200),
        ];
    }

    /**
     * Calculate thumbnail dimensions preserving aspect ratio.
     *
     * @return array{int, int}
     */
    private function calculateDimensions(int $srcW, int $srcH, int $maxW, int $maxH): array
    {
        if ($srcW <= $maxW && $srcH <= $maxH) {
            return [$srcW, $srcH];
        }

        $ratio = min($maxW / $srcW, $maxH / $srcH);

        return [(int) round($srcW * $ratio), (int) round($srcH * $ratio)];
    }
}

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

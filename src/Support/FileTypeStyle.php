<?php

declare(strict_types=1);

namespace VmEngine\Fm\Support;

/**
 * Maps a file extension to its Phosphor icon and tint tone.
 *
 * Tone names correspond to the .fm-tone-{tone} classes in resources/dist/fm.css.
 */
final class FileTypeStyle
{
    /**
     * @return array{icon: string, tone: string}
     */
    public static function for(string $extension): array
    {
        return match (strtolower($extension)) {
            'pdf' => ['icon' => 'ph-file-pdf', 'tone' => 'pdf'],
            'doc', 'docx', 'odt', 'rtf' => ['icon' => 'ph-file-doc', 'tone' => 'doc'],
            'xls', 'xlsx', 'ods', 'csv' => ['icon' => 'ph-file-xls', 'tone' => 'sheet'],
            'ppt', 'pptx', 'odp' => ['icon' => 'ph-file-ppt', 'tone' => 'slide'],
            'zip', 'rar', '7z', 'tar', 'gz' => ['icon' => 'ph-file-zip', 'tone' => 'zip'],
            'mp4', 'avi', 'mov', 'mkv', 'webm' => ['icon' => 'ph-file-video', 'tone' => 'video'],
            'mp3', 'wav', 'ogg', 'm4a' => ['icon' => 'ph-file-audio', 'tone' => 'audio'],
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif' => ['icon' => 'ph-file-image', 'tone' => 'image'],
            'txt', 'md' => ['icon' => 'ph-file-text', 'tone' => 'text'],
            default => ['icon' => 'ph-file', 'tone' => 'other'],
        };
    }
}

<?php

declare(strict_types=1);

namespace VmEngine\Fm\Support;

use VmEngine\Fm\Models\FmFile;

/**
 * Normalises folders and files into the flat array the fm::partials.item-tile
 * and fm::partials.item-row partials render. Label-free on purpose — the
 * partials translate ("Folder", "in :path").
 *
 * @phpstan-type Item array{kind: string, key: string, name: string, size: string, location: string, thumb: string|null, icon: string, tone: string, url: string, preview: string, ext: string, date: string}
 */
final class FmItem
{
    /**
     * @return Item
     */
    public static function dir(string $parentSubPath, string $name): array
    {
        return [
            'kind' => 'dir',
            'key' => $parentSubPath === '' ? $name : $parentSubPath.'/'.$name,
            'name' => $name,
            'size' => '',
            'location' => $parentSubPath,
            'thumb' => null,
            'icon' => 'ph-folder',
            'tone' => 'folder',
            'url' => '',
            'preview' => '',
            'ext' => '',
            'date' => '',
        ];
    }

    /**
     * @return Item
     */
    public static function file(FmFile $file): array
    {
        $style = FileTypeStyle::for($file->extension);

        return [
            'kind' => 'file',
            'key' => (string) $file->id,
            'name' => $file->filename,
            'size' => $file->getHumanSize(),
            'location' => FmPath::parentOf($file->relative_path),
            'thumb' => $file->isImage() ? $file->getThumbnailUrl() : null,
            'icon' => $style['icon'],
            'tone' => $style['tone'],
            'url' => $file->getUrl(),
            'preview' => $file->previewKind() ?? '',
            'ext' => strtoupper($file->extension),
            'date' => $file->created_at->format('d M Y'),
        ];
    }
}

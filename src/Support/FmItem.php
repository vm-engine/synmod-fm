<?php

declare(strict_types=1);

namespace VmEngine\Fm\Support;

use VmEngine\Fm\Models\FmFile;

/**
 * Normalises folders and files into the flat array the fm::partials.item-tile
 * and fm::partials.item-row partials render. Label-free on purpose — the
 * partials translate ("Folder", "in :path").
 *
 * @phpstan-type Item array{kind: string, key: string, name: string, size: string, location: string, thumb: string|null, thumbKind: string, icon: string, tone: string, url: string, preview: string, ext: string, date: string}
 */
final class FmItem
{
    /**
     * @param  string  $name  folder name, or a path relative to $parentSubPath (search results)
     * @return Item
     */
    public static function dir(string $parentSubPath, string $name): array
    {
        $key = $parentSubPath === '' ? $name : $parentSubPath.'/'.$name;

        return [
            'kind' => 'dir',
            'key' => $key,
            'name' => basename($key),
            'size' => '',
            'location' => FmPath::parentOf($key),
            'thumb' => null,
            'thumbKind' => '',
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
            'thumb' => $file->isImage() || $file->has_thumbnail ? $file->getThumbnailUrl() : null,
            // "pdf" | "video" → fm.js renders the thumbnail in the browser and stores it.
            'thumbKind' => $file->has_thumbnail || ! $file->canHaveClientThumbnail() ? '' : ($file->isVideo() ? 'video' : 'pdf'),
            'icon' => $style['icon'],
            'tone' => $style['tone'],
            'url' => $file->getUrl(),
            'preview' => $file->previewKind() ?? '',
            'ext' => strtoupper($file->extension),
            'date' => $file->created_at->format('d M Y'),
        ];
    }
}

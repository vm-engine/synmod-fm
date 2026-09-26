<?php

declare(strict_types=1);

namespace VmEngine\Fm\Support;

/**
 * Sub-path helpers for paths *inside* an fm.json storage root.
 *
 * Every user-supplied sub-path (navigation, tree toggles, paste targets,
 * new-folder parents, remembered session values) goes through clean()
 * before it touches Storage, so "..", "." and empty segments can never
 * escape the configured root.
 */
final class FmPath
{
    /**
     * Normalise a sub-path; returns null when it is unsafe.
     */
    public static function clean(string $path): ?string
    {
        if (str_contains($path, "\0")) {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path), '/');

        if ($path === '') {
            return '';
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return implode('/', $segments);
    }

    /**
     * The path and each of its ancestors, shallowest first: "a/b" → ["a", "a/b"].
     *
     * @return array<int, string>
     */
    public static function ancestors(string $path): array
    {
        if ($path === '') {
            return [];
        }

        $result = [];
        $accumulated = '';

        foreach (explode('/', $path) as $segment) {
            $accumulated = $accumulated === '' ? $segment : $accumulated.'/'.$segment;
            $result[] = $accumulated;
        }

        return $result;
    }

    /**
     * Parent sub-path of a relative file path: "a/b/x.jpg" → "a/b", "x.jpg" → "".
     */
    public static function parentOf(string $relativePath): string
    {
        $parent = dirname($relativePath);

        return $parent === '.' ? '' : $parent;
    }
}

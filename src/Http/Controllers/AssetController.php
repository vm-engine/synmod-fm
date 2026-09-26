<?php

declare(strict_types=1);

namespace VmEngine\Fm\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves synmod-fm's package-owned CSS/JS (resources/dist) without a build step.
 *
 * Only whitelisted files are served (lookup, never path joining), with a
 * one-year immutable cache; url() appends ?v={filemtime} for cache-busting.
 */
final class AssetController
{
    private const FILES = [
        'fm.css' => 'text/css; charset=UTF-8',
        'fm.js' => 'application/javascript; charset=UTF-8',
        // Vendored pdfjs-dist 6.3.289 (legacy build, Apache-2.0 — see pdfjs-LICENSE).
        'pdf.min.mjs' => 'text/javascript; charset=UTF-8',
        'pdf.worker.min.mjs' => 'text/javascript; charset=UTF-8',
    ];

    public function __invoke(string $file): BinaryFileResponse
    {
        abort_unless(array_key_exists($file, self::FILES), 404);

        return response()->file(self::path($file), [
            'Content-Type' => self::FILES[$file],
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public static function url(string $file): string
    {
        $version = (string) (@filemtime(self::path($file)) ?: '0');

        return route('fm.assets', ['file' => $file, 'v' => $version]);
    }

    private static function path(string $file): string
    {
        return dirname(__DIR__, 3).'/resources/dist/'.$file;
    }
}

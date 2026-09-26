<?php

declare(strict_types=1);

namespace VmEngine\Fm\Livewire\Concerns;

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Enums\FmAction;
use VmEngine\Fm\Http\Controllers\AssetController;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Services\ThumbnailService;
use VmEngine\Fm\Support\FmPath;

/**
 * Folder navigation shared by the fm::file-manager and fm::file-picker MFC
 * components: safe sub-path navigation, the lazily expanded sidebar tree and
 * the path-row segments.
 *
 * Using components must declare `public string $currentFolder` (fm.json root)
 * and `public string $subPath` (path inside it).
 *
 * @phpstan-ignore trait.unused
 */
trait BrowsesFolders
{
    /**
     * Expanded tree nodes (sub-paths). Not remembered across visits — it is
     * re-derived from the current position (current folder + its ancestors).
     *
     * @var array<int, string>
     */
    public array $expanded = [];

    public function toggleNode(string $path): void
    {
        $path = FmPath::clean($path);

        if ($path === null || $path === '') {
            return;
        }

        if (in_array($path, $this->expanded, true)) {
            $this->expanded = array_values(array_filter(
                $this->expanded,
                fn (string $open): bool => $open !== $path && ! str_starts_with($open, $path.'/'),
            ));

            return;
        }

        $this->expanded[] = $path;
    }

    /**
     * Store a thumbnail fm.js rendered in the browser for a PDF/video.
     *
     * Scoped to the browsed root (ids are client-supplied) and requires read
     * access to it. Only re-renders on success, so the stored thumbnail
     * replaces the icon; failures stay silent (the icon remains).
     */
    public function storeThumbnail(int $id, string $dataUrl): void
    {
        $user = auth()->user();
        $file = FmFile::active()->where('folder_path', $this->currentFolder)->find($id);

        $stored = $user !== null
            && $file !== null
            && FmConfig::canUserDo($user, $this->currentFolder, FmAction::Read)
            && app(ThumbnailService::class)->storeClientThumbnail($file, $dataUrl);

        if (! $stored) {
            $this->skipRender();
        }
    }

    /**
     * Settings fm.js needs to render thumbnails (the box the server accepts).
     *
     * @return array{width: int, height: int, pdfjsUrl: string, pdfWorkerUrl: string}
     */
    protected function thumbnailJsConfig(): array
    {
        return [
            ...ThumbnailService::clientThumbnailBox(),
            'pdfjsUrl' => AssetController::url('pdf.min.mjs'),
            'pdfWorkerUrl' => AssetController::url('pdf.worker.min.mjs'),
        ];
    }

    /**
     * Move to a sub-path of the current root if it is safe and exists.
     */
    protected function enterPath(string $path): bool
    {
        $clean = FmPath::clean($path);

        if ($clean === null || ($clean !== '' && ! $this->directoryExists($clean))) {
            return false;
        }

        $this->subPath = $clean;
        $this->expandToCurrent();

        return true;
    }

    protected function expandToCurrent(): void
    {
        $this->expanded = array_values(array_unique([
            ...$this->expanded,
            ...FmPath::ancestors($this->subPath),
        ]));
    }

    protected function directoryExists(string $subPath): bool
    {
        $full = $subPath === '' ? $this->currentFolder : $this->currentFolder.'/'.$subPath;

        return Storage::disk(FmConfig::getDisk())->directoryExists($full);
    }

    /**
     * @return array<int, array{name: string, path: string, open: bool, children: array<int, mixed>|null}>
     */
    #[Computed()]
    public function folderTree(): array
    {
        return $this->treeLevel('');
    }

    /**
     * @return array<int, array{name: string, path: string, open: bool, children: array<int, mixed>|null}>
     */
    private function treeLevel(string $parent): array
    {
        $disk = Storage::disk(FmConfig::getDisk());
        $base = $parent === '' ? $this->currentFolder : $this->currentFolder.'/'.$parent;

        if ($this->currentFolder === '' || ! $disk->directoryExists($base)) {
            return [];
        }

        $names = array_map('basename', $disk->directories($base));
        sort($names);

        $nodes = [];

        foreach ($names as $name) {
            $path = $parent === '' ? $name : $parent.'/'.$name;
            $open = in_array($path, $this->expanded, true);

            $nodes[] = [
                'name' => $name,
                'path' => $path,
                'open' => $open,
                'children' => $open ? $this->treeLevel($path) : null,
            ];
        }

        return $nodes;
    }

    /**
     * @return array<int, array{name: string, path: string}>
     */
    #[Computed()]
    public function pathSegments(): array
    {
        return array_map(
            fn (string $path): array => ['name' => basename($path), 'path' => $path],
            FmPath::ancestors($this->subPath),
        );
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Enums\FmAction;
use VmEngine\Fm\Livewire\Concerns\BrowsesFolders;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Services\FileManagerService;
use VmEngine\Fm\Support\FmItem;
use VmEngine\Fm\Support\FmPath;
use VmEngine\Synapse\Services\Helper\Breadcrumbs;
use VmEngine\Synapse\Traits\RemembersQueryParams;

new class extends Component
{
    use BrowsesFolders;
    use RemembersQueryParams {
        mountRemembersQueryParams as protected restoreRememberedParams;
    }
    use WithFileUploads;

    #[Url()]
    public string $currentFolder = '';

    #[Url()]
    public string $subPath = '';

    #[Url()]
    public string $viewMode = 'grid';

    #[Url()]
    public string $search = '';

    #[Url()]
    public string $sortBy = 'filename';

    #[Url()]
    public string $sortDir = 'asc';

    public bool $showTrash = false;

    /** @var array<int, int> */
    public array $selected = [];

    /** @var array<int, string> */
    public array $selectedDirs = [];

    /**
     * FM clipboard — survives navigation and root switches for the session.
     *
     * @var array{mode: string, files: array<int, int>}
     */
    #[Session(key: 'fm.clipboard')]
    public array $clipboard = ['mode' => '', 'files' => []];

    public ?int $detailsId = null;

    /** @var mixed */
    public $uploadFiles = [];

    public string $renameFileId = '';

    public string $renameName = '';

    public string $newFolderName = '';

    /** Sub-path the new folder is created in; null = current folder. */
    public ?string $newFolderParent = null;

    public int $listKey = 0;

    protected function rememberedParams(): array
    {
        return ['viewMode', 'currentFolder', 'subPath', 'sortBy', 'sortDir'];
    }

    public function mount(): void
    {
        synav()->setActiveMenu('backend.fm.index');

        if ($this->currentFolder === '') {
            $this->currentFolder = FmConfig::getFolders()[0]['path'] ?? '';
        }
    }

    /**
     * Runs after mount() (Livewire calls trait mount hooks last): restore the
     * remembered position, then validate it and expand only its ancestors.
     */
    public function mountRemembersQueryParams(): void
    {
        $this->restoreRememberedParams();

        if (FmConfig::getFolderByPath($this->currentFolder) === null) {
            $this->currentFolder = FmConfig::getFolders()[0]['path'] ?? '';
            $this->subPath = '';
        }

        if (! $this->enterPath($this->subPath)) {
            $this->subPath = '';
        }
    }

    public function title(): string
    {
        return __('fm::labels.file_manager');
    }

    public function updatedSearch(): void
    {
        $this->clearSelection();
    }

    // ---------------------------------------------------------- navigation

    public function navigateTo(string $path): void
    {
        if (! $this->enterPath($path)) {
            return;
        }

        $this->showTrash = false;
        $this->detailsId = null;
        $this->clearSelection();
    }

    public function switchFolder(string $path): void
    {
        if ($path === $this->currentFolder) {
            $this->navigateTo('');

            return;
        }

        if (FmConfig::getFolderByPath($path) === null) {
            return;
        }

        $this->currentFolder = $path;
        $this->subPath = '';
        $this->expanded = [];
        $this->showTrash = false;
        $this->detailsId = null;
        $this->clearSelection();
    }

    public function toggleView(): void
    {
        $this->viewMode = $this->viewMode === 'grid' ? 'list' : 'grid';
    }

    public function toggleTrash(): void
    {
        $this->showTrash = ! $this->showTrash;
        $this->detailsId = null;
        $this->clearSelection();
    }

    public function setSort(string $column): void
    {
        if (in_array($column, ['filename', 'size', 'extension', 'created_at'], true)) {
            $this->sortBy = $column;
        }
    }

    public function setSortDir(string $direction): void
    {
        if (in_array($direction, ['asc', 'desc'], true)) {
            $this->sortDir = $direction;
        }
    }

    /**
     * List-view header click: same column flips direction, new column sorts ascending.
     */
    public function applySort(string $column): void
    {
        if (! in_array($column, ['filename', 'size', 'extension', 'created_at'], true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    // ----------------------------------------------------------- selection

    public function selectOnly(string $kind, string $key): void
    {
        $this->clearSelection();

        if ($kind === 'file') {
            $this->selected = [(int) $key];

            if ($this->detailsId !== null) {
                $this->detailsId = (int) $key;
            }

            return;
        }

        $dir = FmPath::clean($key);

        if ($dir !== null && $dir !== '') {
            $this->selectedDirs = [$dir];
        }
    }

    public function toggleSelect(string $kind, string $key): void
    {
        if ($kind === 'file') {
            $id = (int) $key;
            $this->selected = in_array($id, $this->selected, true)
                ? array_values(array_diff($this->selected, [$id]))
                : [...$this->selected, $id];

            return;
        }

        $dir = FmPath::clean($key);

        if ($dir === null || $dir === '') {
            return;
        }

        $this->selectedDirs = in_array($dir, $this->selectedDirs, true)
            ? array_values(array_diff($this->selectedDirs, [$dir]))
            : [...$this->selectedDirs, $dir];
    }

    public function selectAll(): void
    {
        $this->selected = $this->fileList['files']->modelKeys();
        $this->selectedDirs = $this->showTrash
            ? []
            : array_map(fn (string $dir): string => FmItem::dir($this->subPath, $dir)['key'], $this->fileList['dirs']);
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectedDirs = [];
    }

    /**
     * @param  array{kind: string, key: string}  $item
     */
    public function isSelected(array $item): bool
    {
        return $item['kind'] === 'dir'
            ? in_array($item['key'], $this->selectedDirs, true)
            : in_array((int) $item['key'], $this->selected, true);
    }

    /**
     * @param  array{kind: string, key: string}  $item
     */
    public function isCut(array $item): bool
    {
        return $item['kind'] === 'file'
            && $this->clipboard['mode'] === 'cut'
            && in_array((int) $item['key'], $this->clipboard['files'], true);
    }

    // ----------------------------------------------------------- clipboard

    public function clipboardCopy(): void
    {
        $this->fillClipboard('copy');
    }

    public function clipboardCut(): void
    {
        $this->fillClipboard('cut');
    }

    private function fillClipboard(string $mode): void
    {
        if ($this->selected === [] || $this->showTrash) {
            return;
        }

        $this->clipboard = ['mode' => $mode, 'files' => array_values($this->selected)];
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: trans_choice('fm::labels.clipboard_'.$mode, count($this->selected)));
    }

    /**
     * Paste the clipboard into $target (a sub-path of the current root), or
     * into the current folder when null. Files only — folder copy/cut is UI-only.
     */
    public function paste(?string $target = null): void
    {
        $user = auth()->user();
        $ids = array_map('intval', $this->clipboard['files']);
        $targetSubPath = $target === null ? $this->subPath : FmPath::clean($target);

        if (! $user || $ids === [] || $targetSubPath === null || $this->showTrash) {
            return;
        }

        $isCut = $this->clipboard['mode'] === 'cut';
        $action = $isCut ? FmAction::Move : FmAction::Copy;
        $files = FmFile::active()->whereIn('id', $ids)->get();

        $allowed = FmConfig::canUserDo($user, $this->currentFolder, $action)
            && $files->pluck('folder_path')->unique()->every(
                fn (string $source): bool => FmConfig::canUserDo($user, $source, $action)
            );

        if (! $allowed) {
            $this->dispatch('notify', variant: 'danger', title: __('fm::labels.error'), message: __('fm::labels.no_permission'));

            return;
        }

        if ($isCut) {
            // Cutting into the folder a file already lives in is a no-op.
            $files = $files->reject(fn (FmFile $file): bool => $file->folder_path === $this->currentFolder
                && FmPath::parentOf($file->relative_path) === $targetSubPath);
            $this->clipboard = ['mode' => '', 'files' => []];
        }

        if ($files->isEmpty()) {
            $this->clipboard = $isCut ? $this->clipboard : ['mode' => '', 'files' => []];

            return;
        }

        $service = app(FileManagerService::class);

        if ($isCut) {
            $service->move($files->modelKeys(), $this->currentFolder, $targetSubPath);
        } else {
            $service->copy($files->modelKeys(), $this->currentFolder, $targetSubPath);
        }

        $this->clearSelection();
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: trans_choice('fm::labels.paste_success', $files->count()));
        $this->listKey++;
    }

    // ------------------------------------------------------------- details

    public function showDetails(int $id): void
    {
        if (FmFile::whereKey($id)->where('folder_path', $this->currentFolder)->doesntExist()) {
            return;
        }

        $this->detailsId = $id;
        $this->selected = [$id];
        $this->selectedDirs = [];
    }

    public function closeDetails(): void
    {
        $this->detailsId = null;
    }

    // -------------------------------------------------------------- upload

    public function updatedUploadFiles(): void
    {
        if (empty($this->uploadFiles)) {
            return;
        }

        $this->upload();
    }

    public function upload(): void
    {
        $user = auth()->user();

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, FmAction::Upload)) {
            $this->dispatch('notify', variant: 'danger', title: __('fm::labels.error'), message: __('fm::labels.no_permission'));

            return;
        }

        $uploadConfig = FmConfig::getUploadConfig();
        $maxKb = (int) ($uploadConfig['max_size_kb'] ?? 10240);
        $exts = implode(',', $uploadConfig['allowed_extensions'] ?? []);

        try {
            $this->validate(['uploadFiles.*' => "file|max:{$maxKb}|mimes:{$exts}"]);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('fm::labels.upload_failed');
            $this->dispatch('notify', variant: 'danger', title: __('fm::labels.error'), message: $message);
            $this->uploadFiles = [];

            return;
        }

        $service = app(FileManagerService::class);

        foreach ($this->uploadFiles as $file) {
            $service->upload($file, $this->currentFolder, $this->subPath, $user->id);
        }

        $this->uploadFiles = [];
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.upload_success'));
        $this->listKey++;
    }

    // ------------------------------------------------- trash / restore / purge

    public function trash(int $id): void
    {
        if (! $this->authorizeAction(FmAction::Delete)) {
            return;
        }

        $file = $this->filesInRoot([$id])->first();

        if (! $file) {
            return;
        }

        app(FileManagerService::class)->delete($file);
        $this->forget($id);
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.trashed_success'));
        $this->dispatch('synapse-confirmed');
        $this->listKey++;
    }

    public function trashSelected(): void
    {
        if (! $this->authorizeAction(FmAction::Delete)) {
            return;
        }

        $service = app(FileManagerService::class);

        foreach ($this->filesInRoot($this->selected) as $file) {
            $service->delete($file);
        }

        $this->clearSelection();
        $this->detailsId = null;
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.trashed_success'));
        $this->dispatch('synapse-confirmed');
        $this->listKey++;
    }

    public function restore(int $id): void
    {
        $file = $this->filesInRoot([$id])->first();

        if (! $file) {
            return;
        }

        app(FileManagerService::class)->restore($file);
        $this->forget($id);
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.restore_success'));
        $this->dispatch('synapse-confirmed');
        $this->listKey++;
    }

    public function restoreSelected(): void
    {
        $service = app(FileManagerService::class);

        foreach ($this->filesInRoot($this->selected) as $file) {
            $service->restore($file);
        }

        $this->clearSelection();
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.restore_success'));
        $this->listKey++;
    }

    public function purge(int $id): void
    {
        if (! $this->authorizeAction(FmAction::Delete)) {
            return;
        }

        $file = $this->filesInRoot([$id])->first();

        if (! $file) {
            return;
        }

        app(FileManagerService::class)->purge($file);
        $this->forget($id);
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.purge_success'));
        $this->dispatch('synapse-confirmed');
        $this->listKey++;
    }

    public function purgeSelected(): void
    {
        if (! $this->authorizeAction(FmAction::Delete)) {
            return;
        }

        $service = app(FileManagerService::class);

        foreach ($this->filesInRoot($this->selected) as $file) {
            $service->purge($file);
        }

        $this->clearSelection();
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.purge_success'));
        $this->dispatch('synapse-confirmed');
        $this->listKey++;
    }

    /**
     * Files by id that live in the current storage root. Every id-based action
     * goes through this: permissions are checked against $currentFolder and
     * public Livewire properties (selected, detailsId) are client-writable, so
     * an unscoped lookup would let a crafted id reach another root's files.
     *
     * @param  array<int, int>  $ids
     * @return EloquentCollection<int, FmFile>
     */
    private function filesInRoot(array $ids): EloquentCollection
    {
        if ($ids === []) {
            return new EloquentCollection;
        }

        return FmFile::whereIn('id', $ids)->where('folder_path', $this->currentFolder)->get();
    }

    private function forget(int $id): void
    {
        $this->selected = array_values(array_diff($this->selected, [$id]));

        if ($this->detailsId === $id) {
            $this->detailsId = null;
        }
    }

    private function authorizeAction(FmAction $action): bool
    {
        $user = auth()->user();

        if ($user && FmConfig::canUserDo($user, $this->currentFolder, $action)) {
            return true;
        }

        $this->dispatch('notify', variant: 'danger', title: __('fm::labels.error'), message: __('fm::labels.no_permission'));

        return false;
    }

    // ------------------------------------------------------- rename / mkdir

    public function startRename(int $id): void
    {
        $file = $this->filesInRoot([$id])->first();

        if (! $file) {
            return;
        }

        $this->renameFileId = (string) $id;
        $this->renameName = $file->filename;
        $this->dispatch('open-modal-fm-rename');
    }

    public function confirmRename(): void
    {
        if (! $this->authorizeAction(FmAction::Rename)) {
            return;
        }

        $this->validate(['renameName' => 'required|string|max:255']);

        $file = $this->filesInRoot([(int) $this->renameFileId])->first();

        if (! $file) {
            return;
        }

        app(FileManagerService::class)->rename($file, $this->renameName);
        $this->renameFileId = '';
        $this->renameName = '';
        $this->dispatch('close-modal-fm-rename');
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.rename_success'));
        $this->listKey++;
    }

    public function createFolder(): void
    {
        if (! $this->authorizeAction(FmAction::Mkdir)) {
            return;
        }

        $this->validate(['newFolderName' => 'required|string|max:100|alpha_dash']);

        $parent = FmPath::clean($this->newFolderParent ?? $this->subPath);

        if ($parent === null) {
            return;
        }

        app(FileManagerService::class)->createFolder($this->currentFolder, $parent, $this->newFolderName);

        $this->expanded = array_values(array_unique([...$this->expanded, ...FmPath::ancestors($parent)]));
        $this->newFolderName = '';
        $this->newFolderParent = null;
        $this->dispatch('close-modal-fm-new-folder');
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.folder_created'));
        $this->listKey++;
    }

    // ------------------------------------------------------------ computed

    /**
     * @return array{dirs: array<int, string>, files: Collection<int, FmFile>}
     */
    #[Computed()]
    public function fileList(): array
    {
        // No configured root (empty fm.json): never fall back to listing the disk root.
        if ($this->currentFolderConfig === null) {
            return ['dirs' => [], 'files' => new EloquentCollection, 'truncated' => false];
        }

        $service = app(FileManagerService::class);

        if ($this->showTrash) {
            return [
                'dirs' => [],
                'files' => $service->listTrash($this->currentFolder, $this->search, $this->sortBy, $this->sortDir),
                'truncated' => false,
            ];
        }

        return $service->listDirectory($this->currentFolder, $this->subPath, false, $this->search, $this->sortBy, $this->sortDir);
    }

    /**
     * Folders first, then files, as FmItem arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed()]
    public function items(): array
    {
        $dirs = array_map(fn (string $dir): array => FmItem::dir($this->subPath, $dir), $this->fileList['dirs']);
        $files = $this->fileList['files']->map(fn (FmFile $file): array => FmItem::file($file))->all();

        return [...$dirs, ...$files];
    }

    /**
     * @return array{files: int, dirs: int, bytes: int, single: FmFile|null}
     */
    #[Computed()]
    public function selectionSummary(): array
    {
        $files = $this->filesInRoot($this->selected);

        return [
            'files' => $files->count(),
            'dirs' => count($this->selectedDirs),
            'bytes' => (int) $files->sum('size'),
            'single' => $files->count() === 1 && $this->selectedDirs === [] ? $files->first() : null,
        ];
    }

    #[Computed()]
    public function detailsFile(): ?FmFile
    {
        return $this->detailsId === null ? null : $this->filesInRoot([$this->detailsId])->load('creator')->first();
    }

    #[Computed()]
    public function folders(): array
    {
        return FmConfig::getFolders();
    }

    #[Computed()]
    public function currentFolderConfig(): ?array
    {
        return FmConfig::getFolderByPath($this->currentFolder);
    }

    /**
     * @return array<FmAction>
     */
    #[Computed()]
    public function allowedActions(): array
    {
        $user = auth()->user();

        if (! $user || $this->currentFolder === '') {
            return [];
        }

        return FmConfig::getAllowedActions($user, $this->currentFolder);
    }

    public function canDo(string $action): bool
    {
        $fmAction = FmAction::tryFrom($action);

        return $fmAction !== null && in_array($fmAction, $this->allowedActions, true);
    }

    /**
     * @return array{upload: bool, mkdir: bool, rename: bool, move: bool, copy: bool, delete: bool}
     */
    #[Computed()]
    public function permissions(): array
    {
        return [
            'upload' => $this->canDo('upload'),
            'mkdir' => $this->canDo('mkdir'),
            'rename' => $this->canDo('rename'),
            'move' => $this->canDo('move'),
            'copy' => $this->canDo('copy'),
            'delete' => $this->canDo('delete'),
        ];
    }

    /**
     * Config handed to Alpine.data('fmBrowser') via x-data.
     *
     * @return array<string, mixed>
     */
    #[Computed()]
    public function jsConfig(): array
    {
        $upload = FmConfig::getUploadConfig();
        $confirm = fn (string $prefix, string $yes, string $color, string $icon): array => [
            'title' => __('fm::labels.'.$prefix.'_title'),
            'message' => __('fm::labels.'.$prefix.'_message'),
            'confirm' => __('fm::labels.'.$yes),
            'color' => $color,
            'icon' => $icon,
        ];

        return [
            'mode' => 'manager',
            'canUpload' => $this->canDo('upload'),
            'maxSizeKb' => (int) ($upload['max_size_kb'] ?? 10240),
            'allowedExtensions' => array_values($upload['allowed_extensions'] ?? []),
            'accept' => '*',
            'labels' => [
                'cancel' => __('fm::labels.cancel'),
                'error' => __('fm::labels.error'),
                'success' => __('fm::labels.success'),
                'uploadFailed' => __('fm::labels.upload_failed'),
                'uploadTooLarge' => __('fm::labels.upload_too_large'),
                'uploadInvalidType' => __('fm::labels.upload_invalid_type'),
                'urlCopied' => __('fm::labels.url_copied'),
            ],
            'confirm' => [
                'trash' => $confirm('trash', 'yes_trash', 'danger', 'ph ph-trash'),
                'trashSelected' => $confirm('trash_selected', 'yes_trash', 'danger', 'ph ph-trash'),
                'restore' => $confirm('restore', 'yes_restore', 'info', 'ph ph-arrow-counter-clockwise'),
                'purge' => $confirm('purge', 'yes_purge', 'danger', 'ph ph-fire'),
                'purgeSelected' => $confirm('purge_selected', 'yes_purge', 'danger', 'ph ph-fire'),
            ],
        ];
    }

    #[Computed()]
    public function breadcrumbs(): Breadcrumbs
    {
        return Breadcrumbs::make(
            label: __('fm::labels.file_manager'),
            icon: 'ph ph-folder-open',
        );
    }

    public function render()
    {
        return $this->view()->title(page_title($this->title()));
    }
};

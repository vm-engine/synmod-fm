<?php

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Enums\FmAction;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Services\FileManagerService;
use VmEngine\Synapse\Services\Helper\Breadcrumbs;
use VmEngine\Synapse\Traits\RemembersQueryParams;

new class extends Component
{
    use RemembersQueryParams;
    use WithFileUploads;
    use WithPagination;

    #[Url()]
    public string $currentFolder = '';

    #[Url()]
    public string $subPath = '';

    #[Url()]
    public string $viewMode = 'grid';

    #[Url()]
    public string $search = '';

    public bool $showTrash = false;

    /** @var array<int> */
    public array $selected = [];

    /** @var mixed */
    public $uploadFiles = [];

    public string $renameFileId = '';

    public string $renameName = '';

    public string $newFolderName = '';

    public string $moveTargetFolder = '';

    public string $moveTargetSubPath = '';

    public string $moveAction = 'move';

    public int $listKey = 0;

    #[Url()]
    public string $sortBy = 'filename';

    #[Url()]
    public string $sortDir = 'asc';

    protected function rememberedParams(): array
    {
        return ['viewMode', 'currentFolder', 'sortBy', 'sortDir'];
    }

    public function mount(): void
    {
        synav()->setActiveMenu('backend.fm.index');

        // Default to first folder if none selected
        if ($this->currentFolder === '') {
            $folders = FmConfig::getFolders();
            if (! empty($folders)) {
                $this->currentFolder = $folders[0]['path'];
            }
        }

        // Init moveTargetFolder to currentFolder
        if ($this->moveTargetFolder === '') {
            $this->moveTargetFolder = $this->currentFolder;
        }
    }

    public function title(): string
    {
        return __('fm::labels.file_manager');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedCurrentFolder(): void
    {
        $this->subPath = '';
        $this->selected = [];
        $this->resetPage();
        $this->moveTargetFolder = $this->currentFolder;
    }

    public function navigateTo(string $path): void
    {
        $this->subPath = $path;
        $this->selected = [];
        $this->resetPage();
    }

    public function toggleView(): void
    {
        $this->viewMode = $this->viewMode === 'grid' ? 'list' : 'grid';
    }

    public function toggleTrash(): void
    {
        $this->showTrash = ! $this->showTrash;
        $this->selected = [];
        $this->resetPage();
    }

    public function select(int $id): void
    {
        if (in_array($id, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$id]));
        } else {
            $this->selected[] = $id;
        }
    }

    public function selectAll(): void
    {
        $this->selected = $this->fileList['files']->pluck('id')->toArray();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

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
            $this->dispatch('notify', variant: 'danger', title: 'Error', message: __('fm::labels.no_permission'));

            return;
        }

        $uploadConfig = FmConfig::getUploadConfig();
        $maxKb = (int) ($uploadConfig['max_size_kb'] ?? 10240);
        $exts = implode(',', $uploadConfig['allowed_extensions'] ?? []);

        $this->validate([
            'uploadFiles.*' => "file|max:{$maxKb}|mimes:{$exts}",
        ]);

        /** @var FileManagerService $service */
        $service = app(FileManagerService::class);

        foreach ($this->uploadFiles as $file) {
            $service->upload($file, $this->currentFolder, $this->subPath, $user?->id);
        }

        $this->uploadFiles = [];
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.upload_success'));
        $this->listKey++;
    }

    public function trash(int $id): void
    {
        $user = auth()->user();

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, FmAction::Delete)) {
            $this->dispatch('notify', variant: 'danger', title: 'Error', message: __('fm::labels.no_permission'));

            return;
        }

        $file = FmFile::find($id);
        if (! $file) {
            return;
        }

        app(FileManagerService::class)->delete($file);
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.trashed_success'));
        $this->dispatch('synapse-confirmed');
        $this->listKey++;
    }

    public function trashSelected(): void
    {
        $user = auth()->user();

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, FmAction::Delete)) {
            $this->dispatch('notify', variant: 'danger', title: 'Error', message: __('fm::labels.no_permission'));

            return;
        }

        $service = app(FileManagerService::class);
        foreach ($this->selected as $id) {
            $file = FmFile::find($id);
            if ($file) {
                $service->delete($file);
            }
        }

        $this->selected = [];
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.trashed_success'));
        $this->listKey++;
    }

    public function restore(int $id): void
    {
        $file = FmFile::find($id);
        if (! $file) {
            return;
        }

        app(FileManagerService::class)->restore($file);
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.restore_success'));
        $this->dispatch('synapse-confirmed');
        $this->listKey++;
    }

    public function restoreSelected(): void
    {
        $service = app(FileManagerService::class);
        foreach ($this->selected as $id) {
            $file = FmFile::find($id);
            if ($file) {
                $service->restore($file);
            }
        }

        $this->selected = [];
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.restore_success'));
        $this->listKey++;
    }

    public function purge(int $id): void
    {
        $user = auth()->user();

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, FmAction::Delete)) {
            $this->dispatch('notify', variant: 'danger', title: 'Error', message: __('fm::labels.no_permission'));

            return;
        }

        $file = FmFile::find($id);
        if (! $file) {
            return;
        }

        app(FileManagerService::class)->purge($file);
        $this->selected = array_values(array_diff($this->selected, [$id]));
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.purge_success'));
        $this->dispatch('synapse-confirmed');
        $this->listKey++;
    }

    public function purgeSelected(): void
    {
        $user = auth()->user();

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, FmAction::Delete)) {
            $this->dispatch('notify', variant: 'danger', title: 'Error', message: __('fm::labels.no_permission'));

            return;
        }

        $service = app(FileManagerService::class);
        foreach ($this->selected as $id) {
            $file = FmFile::find($id);
            if ($file) {
                $service->purge($file);
            }
        }

        $this->selected = [];
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.purge_success'));
        $this->listKey++;
    }

    public function startRename(int $id): void
    {
        $file = FmFile::find($id);
        if (! $file) {
            return;
        }

        $this->renameFileId = (string) $id;
        $this->renameName = $file->filename;
        $this->dispatch('fm-open-rename');
    }

    public function confirmRename(): void
    {
        $user = auth()->user();

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, FmAction::Rename)) {
            $this->dispatch('notify', variant: 'danger', title: 'Error', message: __('fm::labels.no_permission'));

            return;
        }

        $this->validate(['renameName' => 'required|string|max:255']);

        $file = FmFile::find((int) $this->renameFileId);
        if (! $file) {
            return;
        }

        app(FileManagerService::class)->rename($file, $this->renameName);
        $this->renameFileId = '';
        $this->renameName = '';
        $this->dispatch('fm-close-rename');
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.rename_success'));
        $this->listKey++;
    }

    public function createFolder(): void
    {
        $user = auth()->user();

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, FmAction::Mkdir)) {
            $this->dispatch('notify', variant: 'danger', title: 'Error', message: __('fm::labels.no_permission'));

            return;
        }

        $this->validate(['newFolderName' => 'required|string|max:100|alpha_dash']);

        app(FileManagerService::class)->createFolder($this->currentFolder, $this->subPath, $this->newFolderName);
        $this->newFolderName = '';
        $this->dispatch('fm-close-new-folder');
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.folder_created'));
        $this->listKey++;
    }

    public function executeMoveOrCopy(): void
    {
        $user = auth()->user();
        $fmAction = $this->moveAction === 'copy' ? FmAction::Copy : FmAction::Move;

        if (! $user || ! FmConfig::canUserDo($user, $this->currentFolder, $fmAction)) {
            $this->dispatch('notify', variant: 'danger', title: 'Error', message: __('fm::labels.no_permission'));

            return;
        }

        if (empty($this->selected)) {
            return;
        }

        $service = app(FileManagerService::class);

        if ($this->moveAction === 'copy') {
            $service->copy($this->selected, $this->moveTargetFolder, $this->moveTargetSubPath);
        } else {
            $service->move($this->selected, $this->moveTargetFolder, $this->moveTargetSubPath);
        }

        $this->selected = [];
        $this->dispatch('fm-close-move');
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.move_success'));
        $this->listKey++;
    }

    public function applySort(string $column): void
    {
        $allowed = ['filename', 'size', 'extension', 'created_at'];
        if (! in_array($column, $allowed, true)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }
    }

    #[Computed()]
    public function fileList(): array
    {
        /** @var FileManagerService $service */
        $service = app(FileManagerService::class);

        return $service->listDirectory(
            $this->currentFolder,
            $this->subPath,
            $this->showTrash,
            $this->search,
            $this->sortBy,
            $this->sortDir
        );
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

    #[Computed()]
    public function breadcrumbs(): Breadcrumbs
    {
        return Breadcrumbs::make(
            label: __('fm::labels.file_manager'),
            icon: 'fa-solid fa-folder-open',
        );
    }

    #[Computed()]
    public function pathSegments(): array
    {
        if ($this->subPath === '') {
            return [];
        }

        $parts = explode('/', $this->subPath);
        $segments = [];
        $accumulated = '';

        foreach ($parts as $part) {
            $accumulated = $accumulated !== '' ? $accumulated.'/'.$part : $part;
            $segments[] = ['name' => $part, 'path' => $accumulated];
        }

        return $segments;
    }

    /**
     * Get Font Awesome icon class for a given file extension.
     */
    public function fileIcon(string $ext): string
    {
        return match (strtolower($ext)) {
            'pdf' => 'fa-solid fa-file-pdf text-red-500',
            'doc', 'docx' => 'fa-solid fa-file-word text-blue-500',
            'xls', 'xlsx' => 'fa-solid fa-file-excel text-green-600',
            'ppt', 'pptx' => 'fa-solid fa-file-powerpoint text-orange-500',
            'zip', 'rar', '7z', 'tar', 'gz' => 'fa-solid fa-file-zipper text-yellow-600',
            'mp4', 'avi', 'mov', 'mkv' => 'fa-solid fa-file-video text-purple-500',
            'mp3', 'wav', 'ogg' => 'fa-solid fa-file-audio text-indigo-500',
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => 'fa-solid fa-file-image text-pink-500',
            'txt' => 'fa-solid fa-file-lines text-gray-500',
            default => 'fa-solid fa-file text-gray-400',
        };
    }

    public function render()
    {
        return $this->view()->title(page_title($this->title()));
    }
};

<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Enums\FmAction;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Services\FileManagerService;

new class extends Component
{
    use WithFileUploads;

    /** The value synced with the parent via wire:model */
    #[Modelable]
    public string $value = '';

    /** Accepted MIME type filter, e.g. "image/*" or "*" */
    public string $accept = '*';

    /** Unique key to scope open/close events when multiple pickers exist on the same page */
    public string $pickerKey = '';

    /** Label displayed above the input */
    public string $label = '';

    /**
     * Optional: lock the picker to a specific folder path.
     * Must match a folder path registered in fm.json.
     * If invalid, the picker will show an error instead of the file browser.
     */
    public string $folder = '';

    /** Currently browsed folder path */
    public string $currentFolder = '';

    /** Sub-path navigation within folder */
    public string $subPath = '';

    /** Search filter */
    public string $search = '';

    /** @var mixed */
    public $uploadFiles = [];

    /** Set to true when the given $folder parameter is not found in the config */
    public bool $folderError = false;

    public function mount(): void
    {
        if ($this->folder !== '') {
            // Validate the requested folder against config
            if (FmConfig::getFolderByPath($this->folder) === null) {
                $this->folderError = true;

                return;
            }

            $this->currentFolder = $this->folder;

            return;
        }

        // Default to first configured folder
        $folders = FmConfig::getFolders();
        if (! empty($folders)) {
            $this->currentFolder = $folders[0]['path'];
        }
    }

    #[On('fm:open-picker')]
    public function openPicker(string $accept = '*', string $key = ''): void
    {
        if ($key !== '' && $key !== $this->pickerKey) {
            return;
        }

        $this->accept = $accept;
        $eventName = $this->pickerKey !== '' ? 'fm-picker-open-'.$this->pickerKey : 'fm-picker-open';
        $this->dispatch($eventName);
    }

    public function navigateTo(string $path): void
    {
        $this->subPath = $path;
    }

    public function updatedCurrentFolder(): void
    {
        $this->subPath = '';
    }

    public function selectFile(int $id): void
    {
        $file = FmFile::active()->find($id);
        if (! $file) {
            return;
        }

        $url = $file->getUrl();
        $this->value = $url;

        // Dispatch to JS — TinyMCE listener and Alpine.js form fields pick this up
        $this->dispatch('fm:file-selected', url: $url, path: $file->getStoragePath(), fileId: $file->id, key: $this->pickerKey);
        $this->dispatch('fm-picker-close');
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

        try {
            $this->validate([
                'uploadFiles.*' => "file|max:{$maxKb}|mimes:{$exts}",
            ]);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? __('fm::labels.upload_failed');
            $this->dispatch('notify', variant: 'danger', title: __('fm::labels.error'), message: $message);
            $this->uploadFiles = [];

            return;
        }

        /** @var FileManagerService $service */
        $service = app(FileManagerService::class);

        foreach ($this->uploadFiles as $file) {
            $service->upload($file, $this->currentFolder, $this->subPath, $user?->id);
        }

        $this->uploadFiles = [];
        unset($this->fileList);
        $this->dispatch('notify', variant: 'success', title: __('fm::labels.success'), message: __('fm::labels.upload_success'));
    }

    #[Computed()]
    public function uploadConfig(): array
    {
        return FmConfig::getUploadConfig();
    }

    #[Computed()]
    public function canUpload(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return FmConfig::canUserDo($user, $this->currentFolder, FmAction::Upload);
    }

    #[Computed()]
    public function fileList(): array
    {
        /** @var FileManagerService $service */
        $service = app(FileManagerService::class);

        $result = $service->listDirectory(
            $this->currentFolder,
            $this->subPath,
            false,
            $this->search
        );

        if ($this->accept !== '*' && $this->accept !== '') {
            $result['files'] = $result['files']
                ->filter(fn (FmFile $file) => $this->matchesAccept($file))
                ->values();
        }

        return $result;
    }

    private function matchesAccept(FmFile $file): bool
    {
        foreach (explode(',', $this->accept) as $part) {
            $part = trim($part);
            if ($part === '*') {
                return true;
            }
            if (str_ends_with($part, '/*') && str_starts_with($file->mime_type, str_replace('*', '', $part))) {
                return true;
            }
            if (str_starts_with($part, '.') && $file->extension === ltrim($part, '.')) {
                return true;
            }
            if ($file->mime_type === $part) {
                return true;
            }
        }

        return false;
    }

    #[Computed()]
    public function folders(): array
    {
        if ($this->folder !== '') {
            $folderConfig = FmConfig::getFolderByPath($this->folder);

            return $folderConfig !== null ? [$folderConfig] : [];
        }

        return FmConfig::getFolders();
    }

    #[Computed()]
    public function isLocked(): bool
    {
        return $this->folder !== '' && ! $this->folderError;
    }

    #[Computed()]
    public function currentFolderConfig(): ?array
    {
        return FmConfig::getFolderByPath($this->currentFolder);
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

    public function render()
    {
        return $this->view();
    }
};

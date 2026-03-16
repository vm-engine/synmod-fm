<?php

declare(strict_types=1);

use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Attributes\On;
use Livewire\Component;
use VmEngine\Fm\Config\FmConfig;
use VmEngine\Fm\Models\FmFile;
use VmEngine\Fm\Services\FileManagerService;

new class extends Component
{
    /** The value synced with the parent via wire:model */
    #[Modelable]
    public string $value = '';

    /** Accepted MIME type filter, e.g. "image/*" or "*" */
    public string $accept = '*';

    /** Label displayed above the input */
    public string $label = '';

    /** Currently browsed folder path */
    public string $currentFolder = '';

    /** Sub-path navigation within folder */
    public string $subPath = '';

    /** Search filter */
    public string $search = '';

    public function mount(): void
    {
        // Default to first configured folder
        $folders = FmConfig::getFolders();
        if (! empty($folders)) {
            $this->currentFolder = $folders[0]['path'];
        }
    }

    #[On('fm:open-picker')]
    public function openPicker(string $accept = '*'): void
    {
        $this->accept = $accept;
        $this->dispatch('fm-picker-open');
    }

    public function navigateTo(string $path): void
    {
        $this->subPath = $path;
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
        $this->dispatch('fm:file-selected', url: $url, path: $file->getStoragePath());
        $this->dispatch('fm-picker-close');
    }

    public function updatedCurrentFolder(): void
    {
        $this->subPath = '';
    }

    #[Computed()]
    public function fileList(): array
    {
        /** @var FileManagerService $service */
        $service = app(FileManagerService::class);

        return $service->listDirectory(
            $this->currentFolder,
            $this->subPath,
            false,
            $this->search
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

<?php

declare(strict_types=1);

namespace VmEngine\Fm\Console\Commands;

use Illuminate\Console\Command;
use VmEngine\Fm\Enums\FmAction;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class FmSetup extends Command
{
    protected $signature = 'mod-fm:setup';

    protected $description = 'Interactive setup wizard to create or update the fm.json configuration file';

    public function handle(): int
    {
        $configPath = synapps_path('config/fm.json');
        $existing = $this->loadExisting($configPath);
        $isUpdate = file_exists($configPath);

        if ($isUpdate) {
            $this->components->info('Existing fm.json found — updating configuration.');
        } else {
            $this->components->info('Creating new fm.json configuration.');
        }

        $disk = $this->askDisk($existing['disk'] ?? 'public');

        $thumbnailWidth = (int) text(
            label: 'Thumbnail width (px)',
            default: (string) ($existing['image']['thumbnail_width'] ?? 200),
            required: true,
            validate: fn (string $v): ?string => (is_numeric($v) && (int) $v > 0) ? null : 'Must be a positive integer',
        );

        $thumbnailHeight = (int) text(
            label: 'Thumbnail height (px)',
            default: (string) ($existing['image']['thumbnail_height'] ?? 200),
            required: true,
            validate: fn (string $v): ?string => (is_numeric($v) && (int) $v > 0) ? null : 'Must be a positive integer',
        );

        $maxSizeKb = (int) text(
            label: 'Max upload size (KB)',
            default: (string) ($existing['upload']['max_size_kb'] ?? 10240),
            hint: '10240 = 10 MB',
            required: true,
            validate: fn (string $v): ?string => (is_numeric($v) && (int) $v > 0) ? null : 'Must be a positive integer',
        );

        $defaultExtensions = $existing['upload']['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'];

        $extensionsInput = text(
            label: 'Allowed file extensions',
            default: implode(', ', $defaultExtensions),
            hint: 'Comma-separated, e.g. jpg, png, pdf',
            required: true,
        );

        $allowedExtensions = array_values(array_filter(array_map('trim', explode(',', $extensionsInput))));

        $autoPurgeDays = (int) text(
            label: 'Trash auto-purge after (days)',
            default: (string) ($existing['trash']['auto_purge_days'] ?? 30),
            hint: 'Set to 0 to disable auto-purge',
            required: true,
            validate: fn (string $v): ?string => (is_numeric($v) && (int) $v >= 0) ? null : 'Must be 0 or greater',
        );

        $folders = $this->askFolders($existing['folders'] ?? []);

        $config = [
            'disk' => $disk,
            'folders' => $folders,
            'image' => [
                'thumbnail_width' => $thumbnailWidth,
                'thumbnail_height' => $thumbnailHeight,
            ],
            'upload' => [
                'max_size_kb' => $maxSizeKb,
                'allowed_extensions' => $allowedExtensions,
            ],
            'trash' => [
                'auto_purge_days' => $autoPurgeDays,
            ],
        ];

        $dir = dirname($configPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->components->success($isUpdate ? 'fm.json updated successfully.' : 'fm.json created successfully.');
        $this->line("  Saved to: {$configPath}");
        $this->line('  Folders configured: '.count($folders));

        return self::SUCCESS;
    }

    private function askDisk(string $default): string
    {
        $knownDisks = ['public', 'local', 's3'];
        $defaultOption = in_array($default, $knownDisks, true) ? $default : 'other';

        $selected = select(
            label: 'Storage disk',
            options: [
                'public' => 'public',
                'local' => 'local',
                's3' => 's3',
                'other' => 'Other (custom disk name)',
            ],
            default: $defaultOption,
        );

        if ($selected === 'other') {
            return text(
                label: 'Custom disk name',
                default: in_array($default, $knownDisks, true) ? '' : $default,
                required: true,
            );
        }

        return $selected;
    }

    /**
     * @param  array<int, array<string, mixed>>  $existing
     * @return array<int, array<string, mixed>>
     */
    private function askFolders(array $existing): array
    {
        $folders = $existing;

        if (! empty($folders)) {
            $this->components->info('Current folders:');
            foreach ($folders as $i => $folder) {
                $name = $folder['name'] ?? '(unnamed)';
                $path = $folder['path'] ?? '(no path)';
                $this->line("  [{$i}] {$name} → {$path}");
            }
        }

        $options = [];

        if (! empty($folders)) {
            $options['keep'] = 'Keep existing folders ('.count($folders).')';
        }

        $options['add'] = empty($folders) ? 'Add folders' : 'Add more folders';
        $options['replace'] = 'Replace all folders (start fresh)';

        $action = select(
            label: 'Folder configuration',
            options: $options,
            default: empty($folders) ? 'add' : 'keep',
        );

        if ($action === 'replace') {
            $folders = [];
            $action = 'add';
        }

        if ($action === 'add') {
            do {
                $folder = $this->askFolder();
                $folders[] = $folder;
                $this->components->info("Folder '{$folder['name']}' added.");
                $addAnother = confirm('Add another folder?', default: false);
            } while ($addAnother);
        }

        return $folders;
    }

    /**
     * @return array<string, mixed>
     */
    private function askFolder(): array
    {
        $name = text(
            label: 'Folder display name',
            placeholder: 'Public Assets',
            required: true,
        );

        $path = text(
            label: 'Folder storage path',
            placeholder: 'fm/public',
            hint: 'Relative to the storage disk root',
            required: true,
            validate: fn (string $v): ?string => preg_match('/^[a-zA-Z0-9\/\-_]+$/', $v) ? null : 'Use alphanumeric, slashes, hyphens, and underscores only',
        );

        $permissions = $this->askFolderPermissions();

        return [
            'name' => $name,
            'path' => $path,
            'permissions' => $permissions,
        ];
    }

    /**
     * @return array<string, array<string>>
     */
    private function askFolderPermissions(): array
    {
        $permissions = [];
        $allActions = FmAction::values();

        $this->line('  Add role permissions (leave role slug empty to finish):');

        while (true) {
            $role = text(
                label: 'Role slug',
                placeholder: 'admin',
                hint: 'Leave empty to finish adding roles',
            );

            if (trim($role) === '') {
                break;
            }

            $defaultActions = $role === 'admin' ? $allActions : ['read'];

            /** @var array<string> $actions */
            $actions = multiselect(
                label: "Permissions for role '{$role}'",
                options: $allActions,
                default: $defaultActions,
                required: true,
            );

            $permissions[$role] = array_values($actions);
        }

        return $permissions;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadExisting(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return [];
        }

        return json_decode($content, true) ?? [];
    }
}

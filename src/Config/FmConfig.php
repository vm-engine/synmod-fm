<?php

declare(strict_types=1);

namespace VmEngine\Fm\Config;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use VmEngine\Fm\Enums\FmAction;

class FmConfig
{
    private static ?array $config = null;

    /**
     * Load and cache fm.json configuration.
     *
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = synapps_path('config/fm.json');

        if (! file_exists($path)) {
            self::$config = self::defaults();

            return self::$config;
        }

        $json = file_get_contents($path);
        self::$config = json_decode($json, true) ?? self::defaults();

        return self::$config;
    }

    /**
     * Force reload from disk (bypass in-memory cache).
     *
     * @return array<string, mixed>
     */
    public static function reload(): array
    {
        self::$config = null;

        return self::load();
    }

    /**
     * Get configured disk name.
     */
    public static function getDisk(): string
    {
        return self::load()['disk'] ?? 'public';
    }

    /**
     * Get all configured folders.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getFolders(): array
    {
        return self::load()['folders'] ?? [];
    }

    /**
     * Get a specific folder config by its path.
     *
     * @return array<string, mixed>|null
     */
    public static function getFolderByPath(string $path): ?array
    {
        foreach (self::getFolders() as $folder) {
            if (($folder['path'] ?? '') === $path) {
                return $folder;
            }
        }

        return null;
    }

    /**
     * Get upload configuration.
     *
     * @return array<string, mixed>
     */
    public static function getUploadConfig(): array
    {
        return self::load()['upload'] ?? [
            'max_size_kb' => 10240,
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'],
        ];
    }

    /**
     * Get image configuration.
     *
     * @return array<string, mixed>
     */
    public static function getImageConfig(): array
    {
        return self::load()['image'] ?? [
            'thumbnail_width' => 200,
            'thumbnail_height' => 200,
        ];
    }

    /**
     * Get trash configuration.
     *
     * @return array<string, mixed>
     */
    public static function getTrashConfig(): array
    {
        return self::load()['trash'] ?? ['auto_purge_days' => 30];
    }

    /**
     * Check if the given user can perform an action on a folder.
     *
     * @param  array<string>  $extraRoles  Additional role slugs to check (for testing/override)
     */
    public static function canUserDo(Authenticatable $user, string $folderPath, FmAction $action, array $extraRoles = []): bool
    {
        /** @phpstan-ignore-next-line */
        if ($user->isDev()) {
            return true;
        }

        $folder = self::getFolderByPath($folderPath);

        if (! $folder) {
            return false;
        }

        $permissions = $folder['permissions'] ?? [];
        $userRoles = array_merge($extraRoles, self::getUserRoleSlugs($user));

        foreach ($userRoles as $role) {
            if (isset($permissions[$role]) && in_array($action->value, $permissions[$role], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all actions the user is allowed to perform on a folder.
     *
     * @return array<FmAction>
     */
    public static function getAllowedActions(Authenticatable $user, string $folderPath): array
    {
        /** @phpstan-ignore-next-line */
        if ($user->isDev()) {
            return FmAction::cases();
        }

        $folder = self::getFolderByPath($folderPath);

        if (! $folder) {
            return [];
        }

        $permissions = $folder['permissions'] ?? [];
        $userRoles = self::getUserRoleSlugs($user);
        $allowed = [];

        foreach ($userRoles as $role) {
            if (isset($permissions[$role])) {
                foreach ($permissions[$role] as $value) {
                    $action = FmAction::tryFrom($value);
                    if ($action !== null && ! in_array($action, $allowed, true)) {
                        $allowed[] = $action;
                    }
                }
            }
        }

        return $allowed;
    }

    /**
     * Get role slugs for the given user.
     *
     * @return array<string>
     */
    private static function getUserRoleSlugs(Authenticatable $user): array
    {
        if (! ($user instanceof Model)) {
            return [];
        }

        /** @phpstan-ignore-next-line */
        return $user->roles()->pluck('slug')->toArray();
    }

    /**
     * Default configuration when fm.json is missing.
     *
     * @return array<string, mixed>
     */
    private static function defaults(): array
    {
        return [
            'disk' => 'public',
            'folders' => [],
            'image' => ['thumbnail_width' => 200, 'thumbnail_height' => 200],
            'upload' => [
                'max_size_kb' => 10240,
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt'],
            ],
            'trash' => ['auto_purge_days' => 30],
        ];
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use VmEngine\Fm\Config\FmConfig;

beforeEach(function () {
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, null);

    $this->configPath = synapps_path('config/fm.json');
    $this->backupPath = synapps_path('config/fm.json.bak');

    if (file_exists($this->configPath)) {
        copy($this->configPath, $this->backupPath);
        unlink($this->configPath);
    }
});

afterEach(function () {
    if (file_exists($this->backupPath)) {
        rename($this->backupPath, $this->configPath);
    } elseif (file_exists($this->configPath)) {
        unlink($this->configPath);
    }

    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, null);
});

it('creates fm.json with provided values', function () {
    $this->artisan('fm:setup')
        ->expectsQuestion('Storage disk', 'public')
        ->expectsQuestion('Thumbnail width (px)', '150')
        ->expectsQuestion('Thumbnail height (px)', '150')
        ->expectsQuestion('Max upload size (KB)', '5120')
        ->expectsQuestion('Allowed file extensions', 'jpg, png, pdf')
        ->expectsQuestion('Trash auto-purge after (days)', '7')
        ->expectsQuestion('Folder configuration', 'add')
        ->expectsQuestion('Folder display name', 'Uploads')
        ->expectsQuestion('Folder storage path', 'fm/uploads')
        ->expectsQuestion('Role slug', 'admin')
        ->expectsQuestion("Permissions for role 'admin'", ['read', 'upload'])
        ->expectsQuestion('Role slug', '')
        ->expectsConfirmation('Add another folder?', 'no')
        ->assertSuccessful();

    expect(file_exists($this->configPath))->toBeTrue();

    $config = json_decode(file_get_contents($this->configPath), true);

    expect($config['disk'])->toBe('public')
        ->and($config['image']['thumbnail_width'])->toBe(150)
        ->and($config['image']['thumbnail_height'])->toBe(150)
        ->and($config['upload']['max_size_kb'])->toBe(5120)
        ->and($config['upload']['allowed_extensions'])->toBe(['jpg', 'png', 'pdf'])
        ->and($config['trash']['auto_purge_days'])->toBe(7)
        ->and($config['folders'])->toHaveCount(1)
        ->and($config['folders'][0]['name'])->toBe('Uploads')
        ->and($config['folders'][0]['path'])->toBe('fm/uploads')
        ->and($config['folders'][0]['permissions']['admin'])->toBe(['read', 'upload']);
});

it('creates fm.json with multiple folders', function () {
    $this->artisan('fm:setup')
        ->expectsQuestion('Storage disk', 'public')
        ->expectsQuestion('Thumbnail width (px)', '200')
        ->expectsQuestion('Thumbnail height (px)', '200')
        ->expectsQuestion('Max upload size (KB)', '10240')
        ->expectsQuestion('Allowed file extensions', 'jpg, png')
        ->expectsQuestion('Trash auto-purge after (days)', '30')
        ->expectsQuestion('Folder configuration', 'add')
        ->expectsQuestion('Folder display name', 'Public')
        ->expectsQuestion('Folder storage path', 'fm/public')
        ->expectsQuestion('Role slug', '')
        ->expectsConfirmation('Add another folder?', 'yes')
        ->expectsQuestion('Folder display name', 'Private')
        ->expectsQuestion('Folder storage path', 'fm/private')
        ->expectsQuestion('Role slug', '')
        ->expectsConfirmation('Add another folder?', 'no')
        ->assertSuccessful();

    $config = json_decode(file_get_contents($this->configPath), true);

    expect($config['folders'])->toHaveCount(2)
        ->and($config['folders'][0]['name'])->toBe('Public')
        ->and($config['folders'][1]['name'])->toBe('Private');
});

it('uses custom disk when other is selected', function () {
    $this->artisan('fm:setup')
        ->expectsQuestion('Storage disk', 'other')
        ->expectsQuestion('Custom disk name', 'my_disk')
        ->expectsQuestion('Thumbnail width (px)', '200')
        ->expectsQuestion('Thumbnail height (px)', '200')
        ->expectsQuestion('Max upload size (KB)', '10240')
        ->expectsQuestion('Allowed file extensions', 'jpg, png')
        ->expectsQuestion('Trash auto-purge after (days)', '30')
        ->expectsQuestion('Folder configuration', 'add')
        ->expectsQuestion('Folder display name', 'Assets')
        ->expectsQuestion('Folder storage path', 'fm/assets')
        ->expectsQuestion('Role slug', '')
        ->expectsConfirmation('Add another folder?', 'no')
        ->assertSuccessful();

    $config = json_decode(file_get_contents($this->configPath), true);

    expect($config['disk'])->toBe('my_disk');
});

it('loads existing values as defaults when updating', function () {
    $existing = [
        'disk' => 's3',
        'folders' => [],
        'image' => ['thumbnail_width' => 300, 'thumbnail_height' => 300],
        'upload' => ['max_size_kb' => 20480, 'allowed_extensions' => ['jpg', 'png']],
        'trash' => ['auto_purge_days' => 60],
    ];

    file_put_contents($this->configPath, json_encode($existing));

    $this->artisan('fm:setup')
        ->expectsQuestion('Storage disk', 's3')
        ->expectsQuestion('Thumbnail width (px)', '300')
        ->expectsQuestion('Thumbnail height (px)', '300')
        ->expectsQuestion('Max upload size (KB)', '20480')
        ->expectsQuestion('Allowed file extensions', 'jpg, png')
        ->expectsQuestion('Trash auto-purge after (days)', '60')
        ->expectsQuestion('Folder configuration', 'keep')
        ->assertSuccessful();

    $config = json_decode(file_get_contents($this->configPath), true);

    expect($config['disk'])->toBe('s3')
        ->and($config['image']['thumbnail_width'])->toBe(300)
        ->and($config['upload']['max_size_kb'])->toBe(20480)
        ->and($config['trash']['auto_purge_days'])->toBe(60);
});

it('replaces existing folders when replace option is chosen', function () {
    $existing = [
        'disk' => 'public',
        'folders' => [
            ['name' => 'Old Folder', 'path' => 'fm/old', 'permissions' => []],
        ],
        'image' => ['thumbnail_width' => 200, 'thumbnail_height' => 200],
        'upload' => ['max_size_kb' => 10240, 'allowed_extensions' => ['jpg']],
        'trash' => ['auto_purge_days' => 30],
    ];

    file_put_contents($this->configPath, json_encode($existing));

    $this->artisan('fm:setup')
        ->expectsQuestion('Storage disk', 'public')
        ->expectsQuestion('Thumbnail width (px)', '200')
        ->expectsQuestion('Thumbnail height (px)', '200')
        ->expectsQuestion('Max upload size (KB)', '10240')
        ->expectsQuestion('Allowed file extensions', 'jpg')
        ->expectsQuestion('Trash auto-purge after (days)', '30')
        ->expectsQuestion('Folder configuration', 'replace')
        ->expectsQuestion('Folder display name', 'New Folder')
        ->expectsQuestion('Folder storage path', 'fm/new')
        ->expectsQuestion('Role slug', '')
        ->expectsConfirmation('Add another folder?', 'no')
        ->assertSuccessful();

    $config = json_decode(file_get_contents($this->configPath), true);

    expect($config['folders'])->toHaveCount(1)
        ->and($config['folders'][0]['name'])->toBe('New Folder');
});

it('saves valid json to the correct path', function () {
    $this->artisan('fm:setup')
        ->expectsQuestion('Storage disk', 'local')
        ->expectsQuestion('Thumbnail width (px)', '200')
        ->expectsQuestion('Thumbnail height (px)', '200')
        ->expectsQuestion('Max upload size (KB)', '10240')
        ->expectsQuestion('Allowed file extensions', 'jpg, png')
        ->expectsQuestion('Trash auto-purge after (days)', '30')
        ->expectsQuestion('Folder configuration', 'add')
        ->expectsQuestion('Folder display name', 'Files')
        ->expectsQuestion('Folder storage path', 'fm/files')
        ->expectsQuestion('Role slug', '')
        ->expectsConfirmation('Add another folder?', 'no')
        ->assertSuccessful();

    $raw = file_get_contents($this->configPath);
    $decoded = json_decode($raw, true);

    expect($decoded)->not->toBeNull()
        ->and($decoded)->toHaveKeys(['disk', 'folders', 'image', 'upload', 'trash']);
});

it('keeps mod-fm:setup as a deprecated alias of fm:setup', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKeys(['fm:setup', 'mod-fm:setup'])
        ->and($commands['mod-fm:setup'])->toBe($commands['fm:setup']);
});

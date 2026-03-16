<?php

declare(strict_types=1);

use VmEngine\Fm\Config\FmConfig;

beforeEach(function () {
    // Reset in-memory cache between tests
    $ref = new ReflectionProperty(FmConfig::class, 'config');
    $ref->setAccessible(true);
    $ref->setValue(null, null);
});

it('returns default disk when fm.json is missing', function () {
    expect(FmConfig::getDisk())->toBe('public');
});

it('returns an array for getFolders', function () {
    expect(FmConfig::getFolders())->toBeArray();
});

it('returns null for unknown folder path', function () {
    expect(FmConfig::getFolderByPath('nonexistent'))->toBeNull();
});

it('returns correct upload config defaults', function () {
    $config = FmConfig::getUploadConfig();

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('max_size_kb')
        ->and($config)->toHaveKey('allowed_extensions');
});

it('returns correct image config defaults', function () {
    $config = FmConfig::getImageConfig();

    expect($config)->toBeArray()
        ->and($config['thumbnail_width'])->toBe(200)
        ->and($config['thumbnail_height'])->toBe(200);
});

it('returns correct trash config defaults', function () {
    $config = FmConfig::getTrashConfig();

    expect($config)->toBeArray()
        ->and($config['auto_purge_days'])->toBe(30);
});

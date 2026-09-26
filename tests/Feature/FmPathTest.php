<?php

declare(strict_types=1);

use VmEngine\Fm\Support\FmPath;

it('normalises safe sub-paths', function (string $input, string $expected) {
    expect(FmPath::clean($input))->toBe($expected);
})->with([
    'empty' => ['', ''],
    'slashes only' => ['/', ''],
    'simple' => ['docs', 'docs'],
    'nested' => ['docs/2026', 'docs/2026'],
    'trims slashes' => ['/docs/2026/', 'docs/2026'],
    'backslashes' => ['docs\\2026', 'docs/2026'],
]);

it('rejects unsafe sub-paths', function (string $input) {
    expect(FmPath::clean($input))->toBeNull();
})->with([
    'parent' => ['..'],
    'parent inside' => ['docs/../secret'],
    'backslash parent' => ['docs\\..\\secret'],
    'current dir' => ['./docs'],
    'double slash' => ['docs//2026'],
    'null byte' => ["docs\0"],
]);

it('lists a path and its ancestors, shallowest first', function () {
    expect(FmPath::ancestors('a/b/c'))->toBe(['a', 'a/b', 'a/b/c'])
        ->and(FmPath::ancestors('a'))->toBe(['a'])
        ->and(FmPath::ancestors(''))->toBe([]);
});

it('returns the parent sub-path of a relative file path', function () {
    expect(FmPath::parentOf('a/b/photo.jpg'))->toBe('a/b')
        ->and(FmPath::parentOf('photo.jpg'))->toBe('');
});

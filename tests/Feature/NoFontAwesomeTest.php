<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

it('uses Phosphor, not Font Awesome', function () {
    $root = dirname(__DIR__, 2);
    $hits = [];
    $dirs = array_values(array_filter(["$root/resources", "$root/src"], 'is_dir'));

    $files = iterator_to_array(Finder::create()->files()->in($dirs)->name(['*.php', '*.json', '*.js']), false);

    if (is_file("$root/module.json")) {
        $files[] = new SplFileInfo("$root/module.json");
    }

    foreach ($files as $file) {
        if (preg_match('/\bfa-(solid|regular|brands)\b|\bfa[srb]? fa-[a-z]|\bfa-spin(ner)?\b|\bfa-circle-notch\b/', (string) file_get_contents($file->getPathname())) === 1) {
            $hits[] = str_replace($root.'/', '', $file->getPathname());
        }
    }

    sort($hits);

    expect($hits)->toBe([]);
});

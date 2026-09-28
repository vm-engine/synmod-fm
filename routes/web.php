<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use VmEngine\Fm\Http\Controllers\AssetController;

// Package-owned CSS/JS for the file manager + picker (name: fm.assets, url: /fm/assets/{file}).
Route::get('/assets/{file}', AssetController::class)
    ->where('file', 'fm\.(css|js)|pdf(\.worker)?\.min\.mjs')
    ->name('assets');

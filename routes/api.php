<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use VmEngine\Fm\Http\Controllers\FileController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [FileController::class, 'upload'])->name('upload');
    Route::get('/stream/{id}', [FileController::class, 'stream'])->name('stream');
});

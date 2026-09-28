<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::group([], function () {
    Route::livewire('/', 'fm::file-manager')->name('index')
        ->middleware('can-access:fm.manage');
});

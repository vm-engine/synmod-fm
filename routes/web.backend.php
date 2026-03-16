<?php

use Illuminate\Support\Facades\Route;

Route::group([], function () {
    Route::livewire('/', 'fm::file-manager')->name('index')
        ->middleware('can-access:fm.manage');
});

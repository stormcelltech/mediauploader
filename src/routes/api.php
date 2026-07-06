<?php

use Illuminate\Support\Facades\Route;
use StormcellTech\MediaUploader\Http\Controllers\MediaController;

Route::prefix(config('media-upload.api.prefix', 'media'))
    ->middleware(config('media-upload.api.middleware', ['auth']))
    ->group(function () {
        // List media
        Route::get('/list', [MediaController::class, 'index'])->name('media-upload.list');

        // Search media
        Route::get('/search/{keyword}', [MediaController::class, 'search'])->name('media-upload.search');

        // Get single media
        Route::get('/{media}/get', [MediaController::class, 'show'])->name('media-upload.show');

        // Upload file
        Route::post('/upload', [MediaController::class, 'store'])->name('media-upload.store');

        // Delete media
        Route::delete('/{media}/delete', [MediaController::class, 'destroy'])->name('media-upload.destroy');
    });

<?php

use Illuminate\Support\Facades\Route;
use Palgoal\MediaLibrary\Http\Controllers\MediaController;

/*
|--------------------------------------------------------------------------
| Media Library Routes
|--------------------------------------------------------------------------
|
| Registered by MediaLibraryServiceProvider under the prefix/middleware
| configured in config/media-library.php. All route names are prefixed
| with "media-library." (e.g. media-library.media.index).
|
*/

// Full library page (Blade). Regular browser navigation has no
// "Accept: application/json" header, so MediaController@index will
// return the view() instead of the JSON payload automatically.
Route::get('/', [MediaController::class, 'index'])->name('page');

Route::prefix('media')->name('media.')->group(function () {
    Route::get('/', [MediaController::class, 'index'])->name('index');
    Route::post('/', [MediaController::class, 'store'])->name('store');
    Route::delete('/bulk', [MediaController::class, 'bulkDestroy'])->name('bulk-destroy');
    Route::get('/{id}', [MediaController::class, 'show'])->name('show');
    Route::get('/{id}/edit', [MediaController::class, 'edit'])->name('edit');
    Route::match(['put', 'patch'], '/{id}', [MediaController::class, 'update'])->name('update');
    Route::delete('/{id}', [MediaController::class, 'destroy'])->name('destroy');
});

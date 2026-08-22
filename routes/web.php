<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/entities', [DashboardController::class, 'entitiesIndex'])->name('entities.index');
Route::get('/materials', [DashboardController::class, 'materialsIndex'])->name('materials.index');

Route::prefix('collections')->name('collections.')->group(function () {
    Route::get('/', [CollectionController::class, 'index'])->name('index');
    Route::get('/create', [CollectionController::class, 'create'])->name('create');
    Route::post('/', [CollectionController::class, 'store'])->name('store');
    Route::get('/{collection}', [CollectionController::class, 'show'])->name('show');
});

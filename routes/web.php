<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RmDashboardController;
use Illuminate\Support\Facades\Route;

// Public - boss/anyone can view the dashboard and browse submissions, but
// cannot submit data anymore. Data entry requires an RM login.
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/entities', [DashboardController::class, 'entitiesIndex'])->name('entities.index');
Route::get('/materials', [DashboardController::class, 'materialsIndex'])->name('materials.index');
Route::get('/ministries', [DashboardController::class, 'ministriesIndex'])->name('ministries.index');

Route::prefix('collections')->name('collections.')->group(function () {
    Route::get('/', [CollectionController::class, 'index'])->name('index');
    Route::get('/{collection}', [CollectionController::class, 'show'])->name('show');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// RM area - each RM sees only their own submissions and can record new
// collections. Admins can reach the same area too (useful for testing/
// helping an RM), gated by role:rm,admin.
Route::prefix('rm')->name('rm.')->middleware(['auth', 'role:rm,admin'])->group(function () {
    Route::get('/', [RmDashboardController::class, 'index'])->name('dashboard');
    Route::get('/collections/create', [RmDashboardController::class, 'create'])->name('collections.create');
    Route::post('/collections', [RmDashboardController::class, 'store'])->name('collections.store');
});

// Admin area - manage RM accounts and review the audit log.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::post('/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
    Route::get('/audit-log', [AdminController::class, 'auditLog'])->name('audit-log');
});

<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientReportController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NawiriTreasuryController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\RmDashboardController;
use Illuminate\Support\Facades\Route;

// Public - boss/anyone can view the dashboard and browse submissions, but
// cannot submit data anymore. Data entry requires an RM login.
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/entities', [DashboardController::class, 'entitiesIndex'])->name('entities.index');
Route::get('/materials', [DashboardController::class, 'materialsIndex'])->name('materials.index');
Route::get('/ministries', [DashboardController::class, 'ministriesIndex'])->name('ministries.index');
Route::get('/ministries/{ministry}', [DashboardController::class, 'ministryShow'])->name('ministries.show');
Route::get('/ministries/{ministry}/departments/{department}', [DashboardController::class, 'departmentShow'])->name('ministries.departments.show');
Route::get('/state-corporations', [DashboardController::class, 'stateCorporationsIndex'])->name('state-corporations.index');
Route::get('/state-corporations/export', [DashboardController::class, 'stateCorporationsExport'])->name('state-corporations.export');
Route::get('/state-corporations/{stateCorporation}', [DashboardController::class, 'stateCorporationShow'])->name('state-corporations.show');
Route::get('/relationship-managers', [DashboardController::class, 'relationshipManagersIndex'])->name('relationship-managers.index');
Route::get('/supervisors', [DashboardController::class, 'supervisorsIndex'])->name('supervisors.index');
Route::get('/material-items', [DashboardController::class, 'materialItemsIndex'])->name('material-items.index');
Route::get('/feasibility-study', [DashboardController::class, 'feasibilityStudyIndex'])->name('feasibility-study.index');
Route::get('/reports', [ClientReportController::class, 'all'])->name('reports.index');

// Public, PIN-gated (not a login - a system-generated PIN handed to the
// boss, per his brief, since admin accounts are shared among several
// people). See RequisitionController's class docblock.
Route::get('/facilitation', [RequisitionController::class, 'publicIndex'])->name('requisitions.public');
Route::post('/facilitation/unlock', [RequisitionController::class, 'unlockPublic'])->name('requisitions.public.unlock');

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

// A requester's own facilitation (transport/airtime) requests - RMs,
// Supervisors and Office Admins all request for themselves; admins can
// reach it too (harmless, they just won't have anything to request in
// practice).
Route::prefix('requisitions')->name('requisitions.')->middleware(['auth', 'role:rm,supervisor,office_admin,admin'])->group(function () {
    Route::get('/', [RequisitionController::class, 'mine'])->name('mine');
    Route::post('/', [RequisitionController::class, 'store'])->name('store');
});

// Admin area - manage RM accounts and review the audit log. Supervisors get
// the same access as admins here (2026-09-05 decision), just under their
// own login so their actions are attributed to them, not shared credentials.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,supervisor'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::get('/users/create', [AdminController::class, 'createUser'])->name('users.create');
    Route::post('/users', [AdminController::class, 'storeUser'])->name('users.store');
    Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
    Route::put('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
    Route::post('/users/{user}/toggle', [AdminController::class, 'toggleUser'])->name('users.toggle');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
    Route::get('/audit-log', [AdminController::class, 'auditLog'])->name('audit-log');
    Route::get('/rm-performance', [AdminController::class, 'rmPerformance'])->name('rm-performance');

    Route::get('/assign-rms', [AdminController::class, 'assignRms'])->name('assign-rms');
    Route::post('/assign-rms/ministries/distribute', [AdminController::class, 'distributeMinistries'])->name('assign-rms.ministries.distribute');
    Route::post('/assign-rms/ministries/{ministry}', [AdminController::class, 'assignMinistryRm'])->name('assign-rms.ministries.update');
    Route::post('/assign-rms/clients/distribute', [AdminController::class, 'distributeClients'])->name('assign-rms.clients.distribute');
    Route::post('/assign-rms/clients/{stateCorporation}', [AdminController::class, 'assignClientRm'])->name('assign-rms.clients.update');
    Route::post('/assign-rms/supervisor/{user}', [AdminController::class, 'assignRmSupervisor'])->name('assign-rms.supervisor.update');

    Route::get('/clients/{client}/reports', [ClientReportController::class, 'index'])->name('clients.reports.index');
    Route::post('/clients/{client}/reports', [ClientReportController::class, 'store'])->name('clients.reports.store');

    Route::get('/reports/{report}/edit', [ClientReportController::class, 'editReport'])->name('reports.edit');
    Route::put('/reports/{report}', [ClientReportController::class, 'updateReport'])->name('reports.update');
});

// The treasury credentials can move money, so only a full admin may view
// or replace them. Supervisors and Office Admins remain outside this area.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/nawiri-treasury', [NawiriTreasuryController::class, 'edit'])->name('nawiri-treasury.edit');
    Route::put('/nawiri-treasury', [NawiriTreasuryController::class, 'update'])->name('nawiri-treasury.update');
});

// Requisition approvals - admin, supervisor, AND office_admin (2026-09-19)
// can all view/approve/decline/pay. Kept as its own group (still under
// /admin/... URLs for continuity) rather than inside the main admin group
// above, since an Office Admin should NOT reach the rest of /admin
// (clients, ministries, team accounts, audit log) - see the
// ROLE_OFFICE_ADMIN docblock on the User model.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin,supervisor,office_admin'])->group(function () {
    Route::get('/requisitions', [RequisitionController::class, 'adminIndex'])->name('requisitions.index');
    Route::get('/requisitions/export', [RequisitionController::class, 'exportApproved'])->name('requisitions.export');
    Route::post('/requisitions/pin/regenerate', [RequisitionController::class, 'regeneratePin'])->name('requisitions.pin.regenerate');
    Route::post('/requisitions/{requisition}/transport/approve', [RequisitionController::class, 'approveTransport'])->name('requisitions.transport.approve');
    Route::post('/requisitions/{requisition}/transport/decline', [RequisitionController::class, 'declineTransport'])->name('requisitions.transport.decline');
    Route::post('/requisitions/{requisition}/transport/pay', [RequisitionController::class, 'payTransport'])->name('requisitions.transport.pay');
    Route::post('/requisitions/{requisition}/airtime/approve', [RequisitionController::class, 'approveAirtime'])->name('requisitions.airtime.approve');
    Route::post('/requisitions/{requisition}/airtime/decline', [RequisitionController::class, 'declineAirtime'])->name('requisitions.airtime.decline');
    Route::post('/requisitions/{requisition}/airtime/pay', [RequisitionController::class, 'payAirtime'])->name('requisitions.airtime.pay');
    Route::post('/requisition-payments/{payment}/reconcile', [RequisitionController::class, 'reconcilePayment'])->name('requisition-payments.reconcile');
});

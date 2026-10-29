<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\AnnouncementController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.attempt');
Route::get('/dashboard', [AuthController::class, 'dashboard'])->middleware(['auth', 'admin'])->name('dashboard');
Route::put('/admin/account/password', [AuthController::class, 'updatePassword'])->middleware('auth')->name('admin.account.password');
Route::middleware(['auth', 'distribution-operator'])->group(function () {
    Route::get('/admin/distribution', [AdminController::class, 'distribution'])->name('admin.distribution');
    Route::get('/admin/distribution/lookup', [AdminController::class, 'lookupBeneficiaryForCheckIn'])->name('admin.distribution.lookup');
    Route::get('/admin/support', [SupportController::class, 'index'])->name('admin.support');
    Route::post('/admin/support', [SupportController::class, 'store'])->name('admin.support.store');
});
Route::patch('/admin/support/{ticket}', [SupportController::class, 'update'])
    ->middleware(['auth', 'admin'])->name('admin.support.update');
Route::middleware(['auth', 'distribution-staff'])->group(function () {
    Route::get('/admin/announcements', [AnnouncementController::class, 'index'])->name('admin.announcements');
    Route::post('/admin/announcements', [AnnouncementController::class, 'store'])->name('admin.announcements.store');
    Route::get('/admin/inventory', [AdminController::class, 'inventory'])->name('admin.inventory');
    Route::post('/admin/inventory/{id}/adjust-stock', [AdminController::class, 'adjustInventoryStock'])->name('admin.inventory.adjust-stock');
    Route::get('/admin/deliveries', [AdminController::class, 'deliveries'])->name('admin.deliveries');
    Route::post('/admin/deliveries', [AdminController::class, 'storeDelivery'])->name('admin.deliveries.store');
});
Route::post('/admin/distribution', [AdminController::class, 'storeDistribution'])
    ->middleware(['auth', 'admin'])->name('admin.distribution.store');
Route::post('/admin/distribution/release-pending', [AdminController::class, 'releasePendingDistribution'])
    ->middleware(['auth', 'distribution-staff'])->name('admin.distribution.release-pending');
Route::middleware(['auth', 'admin'])->group(function () {
    Route::delete('/admin/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('admin.announcements.destroy');
    Route::get('/admin/beneficiaries', [AdminController::class, 'beneficiaries'])->name('admin.beneficiaries');
    Route::post('/admin/beneficiaries', [AdminController::class, 'storeBeneficiary'])->name('admin.beneficiaries.store');
    Route::put('/admin/beneficiaries/{id}', [AdminController::class, 'updateBeneficiary'])->name('admin.beneficiaries.update');
    Route::delete('/admin/beneficiaries/{id}', [AdminController::class, 'deleteBeneficiary'])->name('admin.beneficiaries.delete');
    Route::post('/admin/beneficiaries/bulk-delete', [AdminController::class, 'bulkDeleteBeneficiaries'])->name('admin.beneficiaries.bulk-delete');
    Route::post('/admin/beneficiaries/bulk-status', [AdminController::class, 'bulkStatusChange'])->name('admin.beneficiaries.bulk-status');
    Route::get('/admin/beneficiaries/export', [AdminController::class, 'exportBeneficiaries'])->name('admin.beneficiaries.export');
    Route::get('/admin/users', [AdminController::class, 'users'])->name('admin.users');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::put('/admin/users/{id}', [AdminController::class, 'updateUser'])->name('admin.users.update');
    Route::delete('/admin/users/{id}', [AdminController::class, 'deleteUser'])->name('admin.users.delete');
    Route::get('/admin/search', [AdminController::class, 'search'])->name('admin.search');
    Route::post('/admin/inventory', [AdminController::class, 'storeInventory'])->name('admin.inventory.store');
    Route::put('/admin/inventory/{id}', [AdminController::class, 'updateInventory'])->name('admin.inventory.update');
    Route::delete('/admin/inventory/{id}', [AdminController::class, 'deleteInventory'])->name('admin.inventory.delete');
    Route::get('/admin/packages', [AdminController::class, 'packages'])->name('admin.packages');
    Route::post('/admin/packages', [AdminController::class, 'storePackage'])->name('admin.packages.store');
    Route::put('/admin/packages/{id}', [AdminController::class, 'updatePackage'])->name('admin.packages.update');
    Route::delete('/admin/packages/{id}', [AdminController::class, 'deletePackage'])->name('admin.packages.delete');
    Route::get('/admin/qr-codes', [AdminController::class, 'qrCodes'])->name('admin.qr-codes');
    Route::post('/admin/qr-codes/generate/{id}', [AdminController::class, 'generateQRCode'])->name('admin.qr-codes.generate');
    Route::get('/admin/qr-codes/download/{id}', [AdminController::class, 'downloadQRCode'])->name('admin.qr-codes.download');
    Route::get('/admin/lost-qr', [AdminController::class, 'lostQr'])->name('admin.lost-qr');
    Route::post('/admin/lost-qr/report/{id}', [AdminController::class, 'reportLostQr'])->name('admin.lost-qr.report');
    Route::put('/admin/distribution/{id}', [AdminController::class, 'updateDistribution'])->name('admin.distribution.update');
    Route::delete('/admin/distribution/{id}', [AdminController::class, 'deleteDistribution'])->name('admin.distribution.delete');
    Route::get('/admin/reports/inventory/export', [AdminController::class, 'exportInventoryReport'])->name('admin.reports.inventory-export');
    Route::get('/admin/reports/export', [AdminController::class, 'exportReport'])->name('admin.reports.export');
    Route::get('/admin/reports', [AdminController::class, 'reports'])->name('admin.reports');
    Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs'])->name('admin.audit-logs');
Route::match(['get', 'post'], '/admin/settings', [AdminController::class, 'settings'])->name('admin.settings');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

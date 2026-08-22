<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::get('/dashboard', [AuthController::class, 'dashboard'])->middleware('auth')->name('dashboard');
Route::get('/admin/beneficiaries', [AdminController::class, 'beneficiaries'])->middleware('auth')->name('admin.beneficiaries');
Route::post('/admin/beneficiaries', [AdminController::class, 'storeBeneficiary'])->middleware('auth')->name('admin.beneficiaries.store');
Route::put('/admin/beneficiaries/{id}', [AdminController::class, 'updateBeneficiary'])->middleware('auth')->name('admin.beneficiaries.update');
Route::delete('/admin/beneficiaries/{id}', [AdminController::class, 'deleteBeneficiary'])->middleware('auth')->name('admin.beneficiaries.delete');
Route::get('/admin/inventory', [AdminController::class, 'inventory'])->middleware('auth')->name('admin.inventory');
Route::post('/admin/inventory', [AdminController::class, 'storeInventory'])->middleware('auth')->name('admin.inventory.store');
Route::put('/admin/inventory/{id}', [AdminController::class, 'updateInventory'])->middleware('auth')->name('admin.inventory.update');
Route::delete('/admin/inventory/{id}', [AdminController::class, 'deleteInventory'])->middleware('auth')->name('admin.inventory.delete');
Route::get('/admin/packages', [AuthController::class, 'module'])->defaults('module', 'packages')->middleware('auth')->name('admin.packages');
Route::get('/admin/qr-codes', [AuthController::class, 'module'])->defaults('module', 'qr-codes')->middleware('auth')->name('admin.qr-codes');
Route::get('/admin/lost-qr', [AuthController::class, 'module'])->defaults('module', 'lost-qr')->middleware('auth')->name('admin.lost-qr');
Route::get('/admin/distribution', [AdminController::class, 'distribution'])->middleware('auth')->name('admin.distribution');
Route::post('/admin/distribution', [AdminController::class, 'storeDistribution'])->middleware('auth')->name('admin.distribution.store');
Route::get('/admin/reports', [AuthController::class, 'module'])->defaults('module', 'reports')->middleware('auth')->name('admin.reports');
Route::get('/admin/audit-logs', [AuthController::class, 'module'])->defaults('module', 'audit-logs')->middleware('auth')->name('admin.audit-logs');
Route::get('/admin/settings', [AuthController::class, 'module'])->defaults('module', 'settings')->middleware('auth')->name('admin.settings');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

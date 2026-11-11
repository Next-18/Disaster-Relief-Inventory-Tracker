<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\BeneficiaryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DistributionController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\SupportTicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['auth:sanctum', 'mobile-user'])->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication routes
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware(['auth:sanctum', 'mobile-user']);
Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware(['auth:sanctum', 'mobile-user']);
Route::get('/notifications', [NotificationController::class, 'index'])->middleware(['auth:sanctum', 'mobile-user']);
Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->middleware(['auth:sanctum', 'mobile-user']);
Route::get('/announcements', [AnnouncementController::class, 'index'])->middleware(['auth:sanctum', 'mobile-user']);
Route::get('/support/tickets', [SupportTicketController::class, 'index'])->middleware(['auth:sanctum', 'mobile-user']);
Route::post('/support/tickets', [SupportTicketController::class, 'store'])->middleware(['auth:sanctum', 'mobile-user', 'throttle:5,1']);

// Dashboard routes
Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->middleware(['auth:sanctum', 'mobile-user']);

// Beneficiary routes
Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->middleware(['auth:sanctum', 'mobile-user']);
Route::get('/beneficiaries/{id}', [BeneficiaryController::class, 'show'])->middleware(['auth:sanctum', 'mobile-user']);
Route::put('/beneficiaries/{id}', [BeneficiaryController::class, 'update'])->middleware(['auth:sanctum', 'mobile-user']);
Route::get('/beneficiaries/qr/{qrCode}', [BeneficiaryController::class, 'showByQrCode'])->middleware(['auth:sanctum', 'mobile-user', 'throttle:60,1']);

// Inventory routes
Route::get('/inventory', [InventoryController::class, 'index'])->middleware(['auth:sanctum', 'mobile-user', 'distribution-staff']);
Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->middleware(['auth:sanctum', 'mobile-user', 'distribution-staff']);

// Relief Package routes
Route::get('/packages', [PackageController::class, 'index'])->middleware(['auth:sanctum', 'mobile-user']);
Route::get('/packages/available', [PackageController::class, 'available'])->middleware(['auth:sanctum', 'mobile-user']);

// Distribution routes
Route::get('/distributions', [DistributionController::class, 'index'])->middleware(['auth:sanctum', 'mobile-user']);
Route::post('/distributions', [DistributionController::class, 'store'])->middleware(['auth:sanctum', 'mobile-user', 'admin']);
Route::post('/distributions/release-pending', [DistributionController::class, 'releasePending'])->middleware(['auth:sanctum', 'mobile-user', 'distribution-staff']);
Route::get('/distributions/{id}', [DistributionController::class, 'show'])->middleware(['auth:sanctum', 'mobile-user']);
Route::get('/distributions/beneficiary/{beneficiaryId}', [DistributionController::class, 'byBeneficiary'])->middleware(['auth:sanctum', 'mobile-user']);
Route::post('/distributions/record', [DistributionController::class, 'record'])->middleware(['auth:sanctum', 'mobile-user', 'admin']);

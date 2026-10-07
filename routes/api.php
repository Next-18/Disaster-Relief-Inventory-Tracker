<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BeneficiaryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DistributionController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\PackageController;
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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Authentication routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

// Dashboard routes
Route::get('/dashboard/stats', [DashboardController::class, 'stats'])->middleware('auth:sanctum');

// Beneficiary routes
Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->middleware('auth:sanctum');
Route::get('/beneficiaries/{id}', [BeneficiaryController::class, 'show'])->middleware('auth:sanctum');
Route::get('/beneficiaries/qr/{qrCode}', [BeneficiaryController::class, 'showByQrCode'])->middleware('auth:sanctum');

// Inventory routes
Route::get('/inventory', [InventoryController::class, 'index'])->middleware('auth:sanctum');
Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->middleware('auth:sanctum');

// Relief Package routes
Route::get('/packages', [PackageController::class, 'index'])->middleware('auth:sanctum');
Route::get('/packages/available', [PackageController::class, 'available'])->middleware('auth:sanctum');

// Distribution routes
Route::get('/distributions', [DistributionController::class, 'index'])->middleware('auth:sanctum');
Route::post('/distributions', [DistributionController::class, 'store'])->middleware('auth:sanctum');
Route::get('/distributions/{id}', [DistributionController::class, 'show'])->middleware('auth:sanctum');
Route::get('/distributions/beneficiary/{beneficiaryId}', [DistributionController::class, 'byBeneficiary'])->middleware('auth:sanctum');

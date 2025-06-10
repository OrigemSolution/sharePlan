<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\User\SlotController;

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

// Public auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password/send-otp', [AuthController::class, 'sendForgotPasswordOTP']);
Route::post('/forgot-password/verify-otp', [AuthController::class, 'verifyForgotPasswordOTP']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Protected auth routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::delete('/delete-account', [AuthController::class, 'delete']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

// Service routes
Route::prefix('services')->group(function () {
    // Public routes
    Route::get('/', [ServiceController::class, 'index']);
    Route::get('/{id}', [ServiceController::class, 'show']);

    // Admin only routes
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/add', [ServiceController::class, 'store']);
        Route::put('/update/{id}', [ServiceController::class, 'update']);
        Route::delete('/delete/{id}', [ServiceController::class, 'destroy']);
    });
});

// Slot routes
Route::prefix('slots')->middleware(['auth:sanctum', 'user'])->group(function () {
    Route::get('/', [SlotController::class, 'index']);
    Route::post('/create', [SlotController::class, 'store']);
    Route::get('/{id}', [SlotController::class, 'show']);
    Route::put('/update/{id}', [SlotController::class, 'update']);
    Route::delete('/delete/{id}', [SlotController::class, 'destroy']);
});

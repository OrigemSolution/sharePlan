<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\ServiceController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\User\SlotController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\PaymentController;

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

// Public slot routes (visible to all)
Route::get('/slots', [SlotController::class, 'index']);
Route::get('/slots/{id}', [SlotController::class, 'show']);

// Guest slot routes (no auth required)
Route::post('/slots/{id}/join-as-guest', [SlotController::class, 'joinAsGuest']);
Route::post('/slots/guest/confirm-payment', [SlotController::class, 'confirmGuestPayment']);

// Protected auth routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'getUser']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::delete('/delete-account', [AuthController::class, 'delete']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    //Service routes
    Route::get('/services', [ServiceController::class, 'index']);
    Route::get('/services/{id}', [ServiceController::class, 'show']);
    // Payment routes
    Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment']);
    Route::post('/payments/verify', [PaymentController::class, 'verifyPayment']);
    
    // Authenticated user slot routes
    Route::post('/slots/add', [SlotController::class, 'create']);
    Route::post('/slots/confirm-payment', [SlotController::class, 'confirmPayment']);
    Route::put('/slots/{id}', [SlotController::class, 'update']);
    Route::delete('/slots/{id}', [SlotController::class, 'destroy']);
});

// Service routes
Route::prefix('services')->group(function () {
    // Admin only routes
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/add', [ServiceController::class, 'store']);
        Route::put('/update/{id}', [ServiceController::class, 'update']);
        Route::delete('/delete/{id}', [ServiceController::class, 'destroy']);
    });
});

// Paystack webhook (no auth required)
Route::post('/payments/webhook', [PaymentController::class, 'handleWebhook']);

// Contact routes
Route::post('/contact', [ContactController::class, 'store']); // Public route for sending messages

// Admin contact routes
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/contact', [ContactController::class, 'index']);
    Route::get('/contact/{id}', [ContactController::class, 'show']);
    Route::delete('/contact/{id}', [ContactController::class, 'destroy']);
    Route::put('/contact/{id}', [ContactController::class, 'update']);

    // Admin user management routes
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
});

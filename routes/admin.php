<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\GradeController;
use App\Http\Controllers\Api\Admin\SubjectController;
use App\Http\Controllers\Api\Admin\PackageController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\PaymentController;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->group(function () {

    // Routes مفتوحة (تسجيل الدخول)
    Route::post('/login', [AuthController::class, 'login']);

    // Routes محمية (تتطلب توكن)
    Route::middleware(['auth:sanctum', 'admin'])->group(function () {

        // ===== Auth =====
        Route::post('/logout', [AuthController::class, 'logout']);

    });
});
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


          // ===== Users Management =====
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);


        Route::get('/stats', [UserController::class, 'stats']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/filter-options', [UserController::class, 'filterOptions']);   



    });
});
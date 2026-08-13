<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// استيراد Routes من الملفات المنفصلة
require base_path('routes/admin.php');
require base_path('routes/teacher.php');
require base_path('routes/student.php');

// أي Routes عامة (زي health check)
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});
use App\Http\Controllers\Api\NotificationController;

Route::middleware('auth:sanctum')->group(function () {
    
    // إشعارات المستخدم
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    
});
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
use App\Http\Controllers\Api\VideoProgressController;

Route::middleware('auth:sanctum')->group(function () {
    
    // إشعارات المستخدم
    Route::prefix('notifications')->group(function(){
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    });
    // ✅ تقدم الفيديوهات (لأي مستخدم: طالب أو أدمن)
    Route::prefix('video-progress')->group(function(){
        Route::post('/', [VideoProgressController::class, 'updateProgress']);
        Route::get('/{videoId}', [VideoProgressController::class, 'getProgress']);
        Route::get('/', [VideoProgressController::class, 'getAllProgress']);
        Route::post('/{videoId}/complete', [VideoProgressController::class, 'markAsCompleted']);
     });
});
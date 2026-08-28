<?php

use App\Http\Controllers\Api\Admin\VideoManagementController;
use App\Http\Controllers\Api\ComplaintController as UserComplaintController;
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
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\User\ComplaintController;
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
    Route::prefix('profile')->group(function(){
        Route::get('/', [ProfileController::class, 'show']);
        Route::post('/', [ProfileController::class, 'update']);
        Route::post('/change-password', [ProfileController::class, 'changePassword']);
        Route::delete('/image', [ProfileController::class, 'deleteImage']);
     });
    Route::get('videos/{id}', [VideoManagementController::class, 'show']);  

     //users Complaints
     
    Route::post('complaints', [UserComplaintController::class, 'store']);
    Route::get('my-complaints', [UserComplaintController::class, 'myComplaints']);
    Route::get('my-complaints/{id}', [UserComplaintController::class, 'show']);
     Route::post('complaints/{id}/reply', [UserComplaintController::class, 'userReply']);

});
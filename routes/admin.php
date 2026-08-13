<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\GradeController;
use App\Http\Controllers\Api\Admin\SubjectController;
use App\Http\Controllers\Api\Admin\PackageController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\PaymentController;
use App\Http\Controllers\Api\Admin\TeacherSubjectController;
use App\Http\Controllers\Api\Admin\VideoManagementController;
use models\Grade;
use App\Models\Subject;
use App\Models\User;




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


        Route::get('/grades', [GradeController::class, 'index']);
        Route::post('/grades', [GradeController::class, 'store']);
        Route::get('/grades/{id}', [GradeController::class, 'show']);
        Route::put('/grades/{id}', [GradeController::class, 'update']);
        Route::delete('/grades/{id}', [GradeController::class, 'destroy']);

        // ===== Subjects Management (المواد) =====
        Route::get('/subjects', [SubjectController::class, 'index']);
        // Route::get('/subjects/options', [SubjectController::class, 'options']);
        Route::post('/subjects', [SubjectController::class, 'store']);
        Route::get('/subjects/{id}', [SubjectController::class, 'show']);
        Route::put('/subjects/{id}', [SubjectController::class, 'update']);
        Route::delete('/subjects/{id}', [SubjectController::class, 'destroy']);
        // Route::post('/subjects/{id}/toggle-status', [SubjectController::class, 'toggleStatus']);

        // ===== Teacher Subject Management (ربط المدرسين بالمواد والصفوف) =====
        Route::get('/teacher-subjects', [TeacherSubjectController::class, 'index']);
        Route::get('/teacher-subjects/form-data', [TeacherSubjectController::class, 'formData']);
        Route::get('/teacher-subjects/available/{teacherId}', [TeacherSubjectController::class, 'availableForTeacher']);
        Route::post('/teacher-subjects/generate-code', [TeacherSubjectController::class, 'generateCode']);
        Route::post('/teacher-subjects', [TeacherSubjectController::class, 'store']);
        // Route::get('/teacher-subjects/{id}', [TeacherSubjectController::class, 'show']);
        // Route::put('/teacher-subjects/{id}', [TeacherSubjectController::class, 'update']);
        // Route::post('/teacher-subjects/{id}/toggle-status', [TeacherSubjectController::class, 'toggleStatus']);
        // Route::get('/teacher-subjects/{id}/students', [TeacherSubjectController::class, 'students']);

        // ===== Teacher Details =====
Route::get('/teachers/{id}/details', [TeacherSubjectController::class, 'teacherDetails']);
Route::delete('/teacher-subjects/{id}', [TeacherSubjectController::class, 'destroy']);

// Route::get('/teachers/{id}/students', [TeacherSubjectController::class, 'teacherStudents']);
// Route::get('/teachers/{teacherId}/subjects/{subjectId}/students', [TeacherSubjectController::class, 'subjectStudents']);
Route::post('/teachers/{id}/toggle-status', [TeacherSubjectController::class, 'toggleTeacherStatus']);


//Video Management


    Route::prefix('videos')->group(function () {

    Route::get('/', [VideoManagementController::class, 'index']);   
    Route::get('/filter-options', [VideoManagementController::class, 'filterOptions']); 
    Route::get('/{id}', [VideoManagementController::class, 'show']);   
    Route::post('/{id}/approve', [VideoManagementController::class, 'approve']); 
    Route::post('/{id}/reject', [VideoManagementController::class, 'reject']); 
    Route::post('/{id}/revision', [VideoManagementController::class, 'requestRevision']); 
    Route::post('/{id}/restore', [VideoManagementController::class, 'restoreToPending']);
    Route::delete('/{id}', [VideoManagementController::class, 'destroy']); 

});
});
   
});
<?php

use App\Http\Controllers\Api\Teacher\AuthController;
use App\Http\Controllers\Api\Teacher\CourseContentController;
use App\Http\Controllers\Api\Teacher\StudentsController;
use Illuminate\Support\Facades\Route;

Route::prefix('teacher')->group(function(){
    Route::post('/login', [AuthController::class, 'login']);
    
    //content course
    Route::middleware(['auth:sanctum','teacher'])->group(function(){
        Route::get('/subjects',[CourseContentController::class,'getTeacherSubjects']);
        Route::post('/subject-grade-id',[CourseContentController::class,'getSubjectGradeId']);
        Route::post('videos/upload-chunk', [CourseContentController::class, 'uploadChunk']);
        Route::post('videos/cancel-upload', [CourseContentController::class, 'cancelUpload']);
        Route::post('videos/check-status', [CourseContentController::class, 'checkUploadStatus']);









        // students
        route::prefix('students')->group(function(){
            Route::get('/stats', [StudentsController::class, 'stats']);
            Route::get('', [StudentsController::class, 'students']);
            Route::get('/{id}', [StudentsController::class, 'studentDetails']);
            Route::get('/filter-options', [StudentsController::class, 'filterOptions']);
        });
       
    });

});
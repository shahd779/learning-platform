<?php

use App\Http\Controllers\Api\Teacher\AuthController;
use App\Http\Controllers\Api\Teacher\CourseContentController;
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

    });

});
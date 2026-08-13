<?php

use App\Http\Controllers\Api\Teacher\AuthController;
use App\Http\Controllers\Api\Teacher\CourseContentController;
use Illuminate\Support\Facades\Route;

Route::prefix('teacher')->group(function(){
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware(['auth:sanctum','teacher'])->group(function(){
        //content course
        Route::get('/subjects',[CourseContentController::class,'getTeacherSubjects']);
        Route::post('/upload-video',[CourseContentController::class,'UploadVideo']);

    });

});
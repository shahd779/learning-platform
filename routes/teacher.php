<?php

use App\Http\Controllers\Api\Teacher\AuthController;
use App\Http\Controllers\Api\Teacher\CommentController;
use App\Http\Controllers\Api\Teacher\CourseContentController;
use App\Http\Controllers\Api\Teacher\FileController;
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
        Route::post('videos/toggle-all-active', [CourseContentController::class, 'toggleAllVideosActive']);
        Route::get('videos/approved', [CourseContentController::class, 'getApprovedVideos']);
        Route::get('videos/status-options', [CourseContentController::class, 'getVideoStatusOptions']);
        Route::post('videos/{id}', [CourseContentController::class, 'updateVideo']);
        Route::post('videos/{id}/toggle-active', [CourseContentController::class, 'toggleVideoActive']);  
        Route::delete('videos/{id}', [CourseContentController::class, 'deleteVideo']);
        Route::get('videos/settings-options', [CourseContentController::class, 'getVideoSettingsOptions']);
        Route::post('videos/{id}/settings', [CourseContentController::class, 'updateVideoSettings']);
        Route::get('videos/pending', [CourseContentController::class, 'getPendingVideos']);
        Route::get('subject-content', [CourseContentController::class, 'getSubjectContent']);


        Route::prefix('files')->group(function(){
            Route::post('/', [FileController::class, 'uploadFile']);
            Route::get('/', [FileController::class, 'getFiles']);
            Route::get('/{id}', [FileController::class, 'showFile']);
            Route::post('/{id}', [FileController::class, 'updateFile']);
            Route::post('/{id}/toggle-active', [FileController::class, 'toggleActive']);
            Route::post('/{id}/toggle-downloadable', [FileController::class, 'toggleDownloadable']);
            Route::delete('/{id}', [FileController::class, 'deleteFile']);

        });
        //comments
        Route::prefix('comments')->group(function () {
            Route::get('videos-filter', [CommentController::class, 'getAllVideos']);
            Route::get('/', [CommentController::class, 'index']);
            Route::get('/video/{videoId}', [CommentController::class, 'getVideoComments']);
            Route::get('/with-teacher-replies', [CommentController::class, 'getCommentsWithTeacherReplies']);
            Route::post('/{commentId}/reply', [CommentController::class, 'replyComment']);
            Route::post('/reply/{replyId}', [CommentController::class, 'updateReply']);
            Route::get('/{commentId}/replies', [CommentController::class, 'getCommentWithReplies']);
            Route::delete('/reply/{replyId}', [CommentController::class, 'deleteReply']);
            Route::delete('/{id}', [CommentController::class, 'deleteComment']);
            Route::delete('/video/{videoId}', [CommentController::class, 'deleteVideoComments']);
});









        // students
        route::prefix('students')->group(function(){
            // Route::get('/stats', [StudentsController::class, 'stats']);
            Route::get('', [StudentsController::class, 'students']);
            Route::get('/filter-options', [StudentsController::class, 'filterOptions']);
            Route::get('/{id}', [StudentsController::class, 'studentDetails']);
            // ===== Student Results =====
            Route::get('/{id}/results', [StudentsController::class, 'studentResults']);
        });
       
    });

});
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
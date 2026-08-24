<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckFileExpiry
{
    public function handle(Request $request, Closure $next)
    {
        // جلب اسم الملف من الرابط
        $fileName = $request->route('fileName');
        $filePath = storage_path('app/public/exports/' . $fileName);

        // التحقق من وجود الملف
        if (!file_exists($filePath)) {
            abort(404, 'الملف غير موجود');
        }

        // التحقق من تاريخ الإنشاء (مثلاً: 24 ساعة)
        $fileCreatedAt = filemtime($filePath);
        if (now()->timestamp - $fileCreatedAt > 86400) {
            abort(410, 'انتهت صلاحية رابط التحميل');
        }

        return $next($request);
    }
}
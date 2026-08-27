<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class LogActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // تسجيل النشاطات بعد تنفيذ الـ Request
        if (Auth::check()) {
            $this->logActivity($request);
        }

        return $response;
    }

    private function logActivity($request)
    {
        $user = Auth::user();
        $route = $request->route();
        $method = $request->method();

        $activity = '';
        $type = '';
        $description = '';

        // تحديد نوع النشاط بناءً على الـ Route
        if (str_contains($route->uri(), 'login')) {
            $activity = 'تسجيل دخول';
            $type = 'login';
            $description = "تم تسجيل دخول المستخدم {$user->name} إلى المنصة";
        } elseif (str_contains($route->uri(), 'logout')) {
            $activity = 'تسجيل خروج';
            $type = 'login';
            $description = "تم تسجيل خروج المستخدم {$user->name} من المنصة";
        } elseif ($method === 'POST' && str_contains($route->uri(), 'users')) {
            $activity = 'إضافة مستخدم جديد';
            $type = 'create';
            $description = "تم إضافة مستخدم جديد بواسطة {$user->name}";
        } elseif ($method === 'PUT' && str_contains($route->uri(), 'users')) {
            $activity = 'تعديل بيانات مستخدم';
            $type = 'update';
            $description = "تم تعديل بيانات مستخدم بواسطة {$user->name}";
        } elseif ($method === 'DELETE' && str_contains($route->uri(), 'users')) {
            $activity = 'حذف مستخدم';
            $type = 'delete';
            $description = "تم حذف مستخدم بواسطة {$user->name}";
        } elseif (str_contains($route->uri(), 'ban') || str_contains($route->uri(), 'toggle-ban')) {
            $activity = 'حظر مستخدم';
            $type = 'ban';
            $description = "تم حظر مستخدم بواسطة {$user->name}";
        } elseif (str_contains($route->uri(), 'unban') || str_contains($route->uri(), 'activate')) {
            $activity = 'فك الحظر';
            $type = 'unban';
            $description = "تم فك الحظر عن مستخدم بواسطة {$user->name}";
        } elseif (str_contains($route->uri(), 'subscriptions')) {
            $activity = 'إدارة اشتراكات';
            $type = 'subscription';
            $description = "تم تعديل اشتراك بواسطة {$user->name}";
        } elseif (str_contains($route->uri(), 'payments')) {
            $activity = 'إدارة مدفوعات';
            $type = 'payment';
            $description = "تم معالجة دفعة بواسطة {$user->name}";
        } elseif (str_contains($route->uri(), 'videos') || str_contains($route->uri(), 'contents')) {
            $activity = 'إدارة محتوى';
            $type = 'content';
            $description = "تم تعديل محتوى بواسطة {$user->name}";
        } else {
            return;
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'activity' => $activity,
            'description' => $description,
            'type' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    // 1. جلب الإشعارات (للمستخدم الحالي - أدمن أو مدرس)
    public function index(Request $request)
    {
        // بنجيب آخر 20 إشعار للمستخدم اللي طلب الـ API
        $notifications = Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        // بنحسب عدد الإشعارات غير المقروءة (عشان الرقم الأحمر اللي فوق)
        $unreadCount = Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => $unreadCount
        ]);
    }

    // 2. تحديد إشعار كمقروء (لما المستخدم يضغط عليه)
    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        $notification->update(['is_read' => true]);

        // لو الإشعار فيه بيانات (مثلاً video_id)، ممكن نرجعه للفرونت عشان يفتح الصفحة
        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read',
            'redirect_data' => $notification->data // هيبعت الـ JSON اللي أنت خزنته
        ]);
    }

    // 3. تحديد كل الإشعارات كمقروءة (لما يضغط يعرض الكل)
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }
}
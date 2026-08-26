<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ActivityLogsExport;

class ActivityLogController extends Controller
{
    /**
     * جلب سجل النشاطات مع فلترة وبحث
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['user']);

        // ✅ فلترة حسب النوع
        if ($request->has('type') && $request->type && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // ✅ فلترة حسب المستخدم (الدور)
        if ($request->has('user_role') && $request->user_role && $request->user_role !== 'all') {
            $query->where('user_role', $request->user_role);
        }

        // ✅ فلترة حسب التاريخ
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // ✅ بحث
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('activity', 'LIKE', '%' . $search . '%')
                  ->orWhere('description', 'LIKE', '%' . $search . '%')
                  ->orWhere('user_name', 'LIKE', '%' . $search . '%');
            });
        }

        // ✅ ترتيب
        $sortField = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->per_page ?? 15;
        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs,
            'filters' => [
                'type' => $request->type,
                'user_role' => $request->user_role,
                'search' => $request->search,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب خيارات الفلترة
     */
    public function filterOptions()
    {
        $types = [
            ['value' => 'all', 'label' => 'كل الأنشطة'],
            ['value' => 'login', 'label' => 'تسجيل دخول / خروج'],
            ['value' => 'create', 'label' => 'إضافة'],
            ['value' => 'update', 'label' => 'تعديل'],
            ['value' => 'delete', 'label' => 'حذف'],
            ['value' => 'ban', 'label' => 'حظر'],
            ['value' => 'unban', 'label' => 'فك الحظر'],
            ['value' => 'subscription', 'label' => 'اشتراكات'],
            ['value' => 'payment', 'label' => 'مدفوعات'],
            ['value' => 'content', 'label' => 'محتوى'],
        ];

        $userRoles = [
            ['value' => 'all', 'label' => 'كل المستخدمين'],
            ['value' => 'admin', 'label' => 'مديرين'],
            ['value' => 'teacher', 'label' => 'مدرسين'],
            ['value' => 'student', 'label' => 'طلاب'],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types,
                'user_roles' => $userRoles,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }


    /**
 * حذف كل سجل النشاطات
 */
public function destroyAll()
{
    $count = ActivityLog::count();
    
    if ($count === 0) {
        return response()->json([
            'success' => false,
            'message' => 'لا توجد نشاطات للحذف'
        ], 422);
    }

    ActivityLog::truncate();

    return response()->json([
        'success' => true,
        'message' => "تم حذف {$count} نشاط بنجاح"
    ], 200, [], JSON_UNESCAPED_UNICODE);
}

    /**
     * تصدير إلى Excel
     */
public function export(Request $request)
    {
        $fileName = 'سجل_النشاطات_' . date('Y_m_d') . '.xlsx';
        $filePath = 'exports/' . $fileName;
        
        // ✅ تخزين الملف مؤقتاً
        Excel::store(new ActivityLogsExport($request), $filePath, 'public');
        
        // ✅ جلب الرابط
        $fileUrl = url('/storage/' . $filePath);
        
        // ✅ تاريخ انتهاء الصلاحية (مثلاً بعد 24 ساعة)
        $expiresAt = now()->addDay();
        
        return response()->json([
            'success' => true,
            'message' => 'تم تصدير الملف بنجاح',
            'data' => [
                'file_name' => $fileName,
                'file_url' => $fileUrl,
                'expires_at' => $expiresAt,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

/**
 * جلب أحدث النشاطات للـ Dashboard
 */
public function latest(Request $request)
{
    $limit = $request->limit ?? 5;
    
    $logs = ActivityLog::with(['user'])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
    
    return response()->json([
        'success' => true,
        'data' => $logs->map(function($log) {
            return [
                'user_name' => $log->user_name,
                'description' => $log->description,
                'time_ago' => $log->created_at->diffForHumans(), // ✅ منذ كام دقيقة
            ];
        })
    ], 200, [], JSON_UNESCAPED_UNICODE);
}




    private function getRoleLabel($role)
    {
        $map = ['admin' => 'مدير', 'teacher' => 'مدرس', 'student' => 'طالب'];
        return $map[$role] ?? $role;
    }
}
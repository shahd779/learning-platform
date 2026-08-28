<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\StudentSubscription;
use App\Models\TeacherSubjectGrade;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    /**
     * جلب نشاطات الطلاب المشتركين مع المدرس
     */
    public function index(Request $request)
    {
        $teacher = auth()->user();

        // ✅ جلب كل المواد اللي المدرس بيدرسها
        $teacherSubjects = TeacherSubjectGrade::where('teacher_id', $teacher->id)
            ->pluck('id')
            ->toArray();

        if (empty($teacherSubjects)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'لا يوجد مواد مسجلة لك'
            ]);
        }

        // ✅ جلب كل الطلاب المشتركين في مواد المدرس
        $studentIds = StudentSubscription::whereIn('teacher_subject_grade_id', $teacherSubjects)
            ->where('status', 'active')
            ->pluck('student_id')
            ->unique()
            ->toArray();

        if (empty($studentIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'لا يوجد طلاب مشتركين معك'
            ]);
        }

        // ✅ جلب نشاطات الطلاب
        $query = ActivityLog::with(['user'])
            ->whereIn('user_id', $studentIds)
            ->where('user_role', 'student');

        // ✅ فلترة حسب نوع النشاط
        if ($request->has('type') && $request->type && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // ✅ فلترة حسب الطالب
        if ($request->has('student_id') && $request->student_id && $request->student_id !== 'all') {
            $query->where('user_id', $request->student_id);
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

        // ✅ إضافة اسم المادة لكل نشاط (اختياري)
        $logs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'user_id' => $log->user_id,
                'user_name' => $log->user_name,
                'user_role' => $log->user_role,
                'activity' => $log->activity,
                'description' => $log->description,
                'type' => $log->type,
                'time_ago' => $log->created_at->diffForHumans(),
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $logs,
            'filters' => [
                'type' => $request->type,
                'student_id' => $request->student_id,
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
        $teacher = auth()->user();

       
       

        
        $types = [
            ['value' => 'all', 'label' => 'كل الأنشطة'],
            ['value' => 'login', 'label' => 'تسجيل دخول / خروج'],
            ['value' => 'video', 'label' => 'مشاهدة فيديو'],
            ['value' => 'assignment', 'label' => 'واجب'],
            ['value' => 'exam', 'label' => 'امتحان'],
            ['value' => 'file', 'label' => 'تحميل ملف'],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

  
}
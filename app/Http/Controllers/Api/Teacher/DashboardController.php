<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;
use App\Models\Video;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\File;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
//TeacherSubjectCodesExport
use App\Exports\TeacherSubjectCodesExport;
use Maatwebsite\Excel\Facades\Excel;




class DashboardController extends Controller
{
    /**
     * لوحة تحكم المدرس (إحصائيات + نشاطات + رسم بياني)
     */
    public function index(Request $request)
    {
        $teacher = auth()->user();

        // ========================================
        // 1️⃣  جلب المواد والصفوف بتاعة المدرس
        // ========================================
        $teacherSubjects = TeacherSubjectGrade::where('teacher_id', $teacher->id)
            ->with(['subject', 'grade'])
            ->get();

        $subjectIds = $teacherSubjects->pluck('id')->toArray();
        $subjectIdsForQuery = !empty($subjectIds) ? $subjectIds : [0];

        // ========================================
        // 2️⃣  البطاقات الرئيسية (Cards)
        // ========================================

        // ✅ إجمالي الطلاب
        $totalStudents = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
            ->where('status', 'active')
            ->distinct('student_id')
            ->count();

        // ✅ إجمالي المواد
        $totalSubjects = $teacherSubjects->count();

        // ✅ إجمالي الصفوف
        $totalGrades = $teacherSubjects->pluck('grade_id')->unique()->count();

        // ✅ إجمالي الفيديوهات
        $totalVideos = Video::where('teacher_id', $teacher->id)
            ->where('status', 'approved')
            ->count();

        // ✅ إجمالي الواجبات
        $totalAssignments = Assignment::where('teacher_id', $teacher->id)
            ->where('status', 'published')
            ->count();

        // ✅ إجمالي الاختبارات
        $totalExams = Exam::where('teacher_id', $teacher->id)
            ->where('status', 'published')
            ->count();

        // ✅ إجمالي الملفات
        $totalFiles = File::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->count();


        // ========================================
        // 4️⃣  أحدث النشاطات (5 نشاطات)
        // ========================================
        $recentActivities = ActivityLog::whereIn('user_id', function($query) use ($subjectIdsForQuery) {
                $query->select('student_id')
                    ->from('student_subscriptions')
                    ->whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                    ->where('status', 'active');
            })
            ->where('user_role', 'student')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($log) {
                return [
                    'id' => $log->id,
                    'user_name' => $log->user_name,
                    'activity' => $log->activity,
                    'description' => $log->description,
                    'type' => $log->type,
                    'time_ago' => $log->created_at->diffForHumans(),
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // ========================================
        // 5️⃣  خيارات الفلترة (الصفوف والمواد)
        // ========================================
        $filterOptions = [
            'grades' => $teacherSubjects->pluck('grade')->unique('id')->values()->map(function($grade) {
                return [
                    'id' => $grade->id,
                    'name' => $grade->name,
                ];
            }),
            'subjects' => $teacherSubjects->pluck('subject')->unique('id')->values()->map(function($subject) {
                return [
                    'id' => $subject->id,
                    'name' => $subject->name,
                ];
            }),
        ];

        // ========================================
        // 6️⃣  الـ Response النهائي
        // ========================================
        return response()->json([
            'success' => true,
            'data' => [
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                ],
                'stats' => [
                    'total_students' => $totalStudents,
                    'total_subjects' => $totalSubjects,
                    'total_grades' => $totalGrades,
                    'total_videos' => $totalVideos,
                    'total_assignments' => $totalAssignments,
                    'total_exams' => $totalExams,
                    'total_files' => $totalFiles,
                ],
                'recent_activities' => $recentActivities,
                'filter_options' => $filterOptions,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }



    public function chartStats(Request $request)
{
    $teacher = auth()->user();

    $teacherSubjects = TeacherSubjectGrade::where('teacher_id', $teacher->id)
        ->with(['subject', 'grade'])
        ->get();

    $subjectIds = $teacherSubjects->pluck('id')->toArray();
    $subjectIdsForQuery = !empty($subjectIds) ? $subjectIds : [0];

    // ✅ فلترة حسب المادة
    if ($request->has('subject_id') && $request->subject_id && $request->subject_id !== 'all') {
        $subjectIdsForQuery = TeacherSubjectGrade::where('teacher_id', $teacher->id)
            ->where('subject_id', $request->subject_id)
            ->pluck('id')
            ->toArray();
    }

    // ✅ فلترة حسب الصف
    if ($request->has('grade_id') && $request->grade_id && $request->grade_id !== 'all') {
        $subjectIdsForQuery = TeacherSubjectGrade::where('teacher_id', $teacher->id)
            ->where('grade_id', $request->grade_id)
            ->pluck('id')
            ->toArray();
    }

    // ✅ الفترة المطلوبة
    $period = $request->period ?? 'weeks';
    $chartData = [];

    switch ($period) {
        case 'week':
            // آخر 7 أيام
            $days = 7;
            $labels = [];
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $labels[] = $date->format('D'); // Mon, Tue, etc.
                $start = $date->startOfDay();
                $end = $date->endOfDay();

                $count = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                    ->where('status', 'active')
                    ->whereBetween('subscribed_at', [$start, $end])
                    ->distinct('student_id')
                    ->count();

                $chartData[] = [
                    'label' => $date->format('D d/m'),
                    'total' => $count,
                    'percentage' => 0, // سنحسبها بعدين
                ];
            }
            break;

        case 'month':
            // آخر 30 يوم
            $days = 30;
            for ($i = $days - 1; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $start = $date->startOfDay();
                $end = $date->endOfDay();

                $count = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                    ->where('status', 'active')
                    ->whereBetween('subscribed_at', [$start, $end])
                    ->distinct('student_id')
                    ->count();

                $chartData[] = [
                    'label' => $date->format('d/m'),
                    'total' => $count,
                    'percentage' => 0,
                ];
            }
            break;

        case 'last_month':
            // الشهر الماضي (أيام)
            $startOfLastMonth = now()->subMonth()->startOfMonth();
            $endOfLastMonth = now()->subMonth()->endOfMonth();
            $daysInMonth = $startOfLastMonth->daysInMonth;

            for ($i = 1; $i <= $daysInMonth; $i++) {
                $date = $startOfLastMonth->copy()->addDays($i - 1);
                $start = $date->startOfDay();
                $end = $date->endOfDay();

                $count = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                    ->where('status', 'active')
                    ->whereBetween('subscribed_at', [$start, $end])
                    ->distinct('student_id')
                    ->count();

                $chartData[] = [
                    'label' => $date->format('d/m'),
                    'total' => $count,
                    'percentage' => 0,
                ];
            }
            break;

        case 'year':
            // آخر 12 شهر
            for ($i = 11; $i >= 0; $i--) {
                $monthStart = now()->subMonths($i)->startOfMonth();
                $monthEnd = now()->subMonths($i)->endOfMonth();

                $count = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                    ->where('status', 'active')
                    ->whereBetween('subscribed_at', [$monthStart, $monthEnd])
                    ->distinct('student_id')
                    ->count();

                $chartData[] = [
                    'label' => $monthStart->format('M Y'),
                    'total' => $count,
                    'percentage' => 0,
                ];
            }
            break;

        case 'weeks':
        default:
            // آخر 4 أسابيع (الافتراضي)
            $weeks = $request->has('weeks') ? (int)$request->weeks : 4;
            for ($i = $weeks - 1; $i >= 0; $i--) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = now()->subWeeks($i)->endOfWeek();

                $count = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                    ->where('status', 'active')
                    ->whereBetween('subscribed_at', [$weekStart, $weekEnd])
                    ->distinct('student_id')
                    ->count();

                $chartData[] = [
                    'label' => 'الأسبوع ' . ($weeks - $i),
                    'total' => $count,
                    'percentage' => 0,
                ];
            }
            break;
    }

    // ✅ حساب النسب المئوية
    $maxTotal = collect($chartData)->max('total');
    foreach ($chartData as &$item) {
        $item['percentage'] = $maxTotal > 0 ? round(($item['total'] / $maxTotal) * 100) : 0;
    }

    return response()->json([
        'success' => true,
        'data' => $chartData,
        'period' => $period,
    ], 200, [], JSON_UNESCAPED_UNICODE);
}




/**
 * دليل الأكواد - عرض كل أكواد المواد للمدرس
 */
public function subjectCodes(Request $request)
{
    $teacher = auth()->user();

    // ✅ جلب المواد اللي المدرس بيدرسها
    $query = TeacherSubjectGrade::where('teacher_id', $teacher->id)
        ->with(['subject', 'grade']);

    // ✅ بحث
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function($q) use ($search) {
            $q->whereHas('subject', function($q2) use ($search) {
                $q2->where('name', 'LIKE', '%' . $search . '%');
            })
            ->orWhereHas('grade', function($q2) use ($search) {
                $q2->where('name', 'LIKE', '%' . $search . '%');
            })
            ->orWhere('access_code', 'LIKE', '%' . $search . '%');
        });
    }

    // ✅ فلترة حسب المادة
    if ($request->has('subject_id') && $request->subject_id && $request->subject_id !== 'all') {
        $query->where('subject_id', $request->subject_id);
    }

    // ✅ فلترة حسب الصف
    if ($request->has('grade_id') && $request->grade_id && $request->grade_id !== 'all') {
        $query->where('grade_id', $request->grade_id);
    }

    // ✅ ترتيب
    $sortField = $request->sort_by ?? 'created_at';
    $sortDirection = $request->sort_direction ?? 'desc';
    $query->orderBy($sortField, $sortDirection);

    // ✅ Pagination
    $perPage = $request->per_page ?? 15;
    $subjectCodes = $query->paginate($perPage);

    // ✅ إضافة عدد الطلاب لكل مادة
    $subjectCodes->getCollection()->transform(function($item) {
        $studentsCount = StudentSubscription::where('teacher_subject_grade_id', $item->id)
            ->where('status', 'active')
            ->distinct('student_id')
            ->count();

        return [
            'id' => $item->id,
            'subject' => $item->subject->name,
            'grade' => $item->grade->name,
            'access_code' => $item->access_code,
            'is_active' => $item->is_active,
            'students_count' => $studentsCount,
            'created_at' => $item->created_at->format('Y-m-d'),
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $subjectCodes,
    ], 200, [], JSON_UNESCAPED_UNICODE);
}



/**
 * تصدير دليل الأكواد إلى Excel
 */
public function exportSubjectCodes(Request $request)
{
    $teacher = auth()->user();

    $fileName = 'دليل_الأكواد_' . date('Y_m_d') . '.xlsx';
    $filePath = 'exports/' . $fileName;

    Excel::store(new TeacherSubjectCodesExport($teacher->id, $request), $filePath, 'public');

    $fileUrl = url('/storage/' . $filePath);

    return response()->json([
        'success' => true,
        'message' => 'تم تصدير الملف بنجاح',
        'data' => [
            'file_name' => $fileName,
            'file_url' => $fileUrl,
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}


}
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

        // ✅ النشاطات (القيودات)
        $totalActivities = ActivityLog::whereIn('user_id', function($query) use ($subjectIdsForQuery) {
                $query->select('student_id')
                    ->from('student_subscriptions')
                    ->whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                    ->where('status', 'active');
            })
            ->where('user_role', 'student')
            ->count();

        // ========================================
        // 3️⃣  رسم بياني لتوزيع الطلاب (آخر 4 أسابيع)
        // ========================================
        $chartData = [];
        for ($i = 3; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();

            $count = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                ->where('status', 'active')
                ->whereBetween('subscribed_at', [$weekStart, $weekEnd])
                ->distinct('student_id')
                ->count();

            $chartData[] = [
                'week' => 'الأسبوع ' . (4 - $i),
                'total' => $count,
            ];
        }

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
                    'total_activities' => $totalActivities,
                ],
                'chart' => $chartData,
                'recent_activities' => $recentActivities,
                'filter_options' => $filterOptions,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * إحصائيات الطلاب حسب الأسبوع (للرسم البياني مع فلترة)
     */
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

        // ✅ عدد الأسابيع (افتراضي 4)
        $weeks = $request->has('weeks') ? (int)$request->weeks : 4;

        $chartData = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = now()->subWeeks($i)->startOfWeek();
            $weekEnd = now()->subWeeks($i)->endOfWeek();

            $count = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                ->where('status', 'active')
                ->whereBetween('subscribed_at', [$weekStart, $weekEnd])
                ->distinct('student_id')
                ->count();

            $percentage = 0;
            if ($i == 0) {
                $percentage = 100;
            } else {
                $firstWeek = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIdsForQuery)
                    ->where('status', 'active')
                    ->whereBetween('subscribed_at', [now()->subWeeks($weeks - 1)->startOfWeek(), now()->subWeeks($weeks - 1)->endOfWeek()])
                    ->distinct('student_id')
                    ->count();

                $percentage = $firstWeek > 0 ? round(($count / $firstWeek) * 100) : 0;
            }

            $chartData[] = [
                'week' => 'الأسبوع ' . ($weeks - $i),
                'total' => $count,
                'percentage' => $percentage,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $chartData,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
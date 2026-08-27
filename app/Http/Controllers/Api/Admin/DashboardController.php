<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\Video;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Package;
use App\Models\Payment;
use App\Models\StudentSubscription;
use App\Models\TeacherSubjectGrade;
use App\Models\ActivityLog;
use App\Models\Complaint;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * جلب كل بيانات الـ Dashboard
     */
    public function index(Request $request)
    {
        // ===== 1. البطاقات الرئيسية =====
        $stats = $this->getMainStats();
        
        // ===== 2. الإيرادات (آخر 30 يوم) =====
        $revenue = $this->getRevenueStats();
        
        // ===== 3. البلاغات الجديدة =====
        $complaints = $this->getRecentComplaints();
        
        // ===== 4. أحدث النشاطات =====
        $activities = $this->getRecentActivities();
        
        // ===== 5. إحصائيات سريعة =====
        $quickStats = $this->getQuickStats( $request);
        
        return response()->json([
            'success' => true,
            'data' => [
                'main_stats' => $stats,
                'revenue' => $revenue,
                'complaints' => $complaints,
                'activities' => $activities,
                'quick_stats' => $quickStats,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * البطاقات الرئيسية
     */

    private function getMainStats()
{
    $currentMonth = now()->month;
    $lastMonth = now()->subMonth()->month;
    $currentYear = now()->year;

    // ... اشتراكات، طلاب، مدرسين

    // ✅ الفيديوهات (approved فقط)
    $totalVideos = Video::where('status', 'approved')->count();
    $lastMonthVideos = Video::where('status', 'approved')
        ->whereMonth('created_at', $lastMonth)
        ->whereYear('created_at', $currentYear)
        ->count();
    $videosChange = $lastMonthVideos > 0 
        ? round((($totalVideos - $lastMonthVideos) / $lastMonthVideos) * 100, 1) 
        : 0;

    return [
        // ... باقي البطاقات
        'total_videos' => [
            'value' => number_format($totalVideos),
            'change' => $videosChange,
            'label' => 'عن الشهر الماضي +' . $videosChange . '%',
        ],
    ];
}
   
 /*   
 * إحصائيات الإيرادات
 */
private function getRevenueStats()
{
    // ✅ إجمالي الإيرادات لهذا الشهر
    $totalRevenue = StudentSubscription::where('status', 'active')
        ->whereMonth('student_subscriptions.created_at', now()->month) // ✅ تحديد الجدول
        ->whereYear('student_subscriptions.created_at', now()->year)   // ✅ تحديد الجدول
        ->join('packages', 'student_subscriptions.package_id', '=', 'packages.id')
        ->sum('packages.price');

    // ✅ إيرادات الشهر الماضي
    $lastMonthRevenue = StudentSubscription::where('status', 'active')
        ->whereMonth('student_subscriptions.created_at', now()->subMonth()->month) // ✅ تحديد الجدول
        ->whereYear('student_subscriptions.created_at', now()->year)               // ✅ تحديد الجدول
        ->join('packages', 'student_subscriptions.package_id', '=', 'packages.id')
        ->sum('packages.price');

    $change = $lastMonthRevenue > 0 
        ? round((($totalRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) 
        : 0;

    // ✅ بيانات الرسم البياني (آخر 30 يوم)
    $chartData = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = now()->subDays($i);
        $dailyRevenue = StudentSubscription::where('status', 'active')
            ->whereDate('student_subscriptions.created_at', $date) // ✅ تحديد الجدول
            ->join('packages', 'student_subscriptions.package_id', '=', 'packages.id')
            ->sum('packages.price');
        
        $chartData[] = [
            'date' => $date->format('d M'),
            'revenue' => (float) $dailyRevenue,
        ];
    }

    return [
        'total' => number_format($totalRevenue, 2) . ' ج.م',
        'change' => $change,
        'label' => 'عن الشهر الماضي +' . $change . '%',
        'chart_data' => $chartData,
    ];
}
    /**
     * البلاغات الجديدة
     */
    private function getRecentComplaints()
{
    $recentComplaints = Complaint::with(['user'])
        ->where('status', 'pending')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    // ✅ جلب أنواع البلاغات بشكل صحيح
    $counts = Complaint::select('type', DB::raw('count(*) as total'))
        ->groupBy('type')
        ->get()
        ->pluck('total', 'type')
        ->toArray();

    return [
        'total' => Complaint::where('status', 'pending')->count(),
        'list' => $recentComplaints->map(function($complaint) {
            return [
                'id' => $complaint->id,
                'user_name' => $complaint->user->name ?? '-',
                'type' => $complaint->type,
                'description' => $complaint->description,
                'created_at' => $complaint->created_at->diffForHumans(),
            ];
        }),
        'types' => [
            'general' => $counts['general'] ?? 0,
            'code' => $counts['code'] ?? 0,
            'payment' => $counts['payment'] ?? 0,
        ],
    ];
}

    /**
     * أحدث النشاطات
     */
    private function getRecentActivities()
    {
        return ActivityLog::with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function($log) {
                return [
                    'user_name' => $log->user_name,
                    'description' => $log->description,
                    'time_ago' => $log->created_at->diffForHumans(),
                ];
            });
    }

    /**
     * إحصائيات سريعة (آخر 7 أيام)
     */



    private function getQuickStats(Request $request)
{
    // ✅ تحديد الفترة الزمنية من الـ Request
    $period = $request->period ?? '7_days'; // القيمة الافتراضية: 7 أيام
    
    // حساب عدد الأيام بناءً على الفترة
    $days = match($period) {
        '7_days' => 7,
        '30_days' => 30,
        '3_months' => 90,
        '6_months' => 180,
        '12_months' => 365,
        default => 7,
    };
    
    $startDate = now()->subDays($days);
    $previousStartDate = now()->subDays($days * 2);
    
    // ===== الواجبات =====
    $assignments = Assignment::where('created_at', '>=', $startDate)->count();
    $previousAssignments = Assignment::whereBetween('created_at', [
        $previousStartDate,
        $startDate
    ])->count();
    $assignmentsChange = $previousAssignments > 0 
        ? round((($assignments - $previousAssignments) / $previousAssignments) * 100, 1) 
        : 0;

    // ===== المستخدمين الجدد (طلاب فقط) =====
    $newUsers = User::where('role', 'student')
        ->where('created_at', '>=', $startDate)
        ->count();
    $previousUsers = User::where('role', 'student')
        ->whereBetween('created_at', [
            $previousStartDate,
            $startDate
        ])->count();
    $usersChange = $previousUsers > 0 
        ? round((($newUsers - $previousUsers) / $previousUsers) * 100, 1) 
        : 0;

    // ===== الاختبارات (باستخدام start_at) =====
    $exams = Exam::where('start_at', '>=', $startDate)
        ->where('status', 'published')
        ->count();
    $previousExams = Exam::whereBetween('start_at', [
        $previousStartDate,
        $startDate
    ])->where('status', 'published')
        ->count();
    $examsChange = $previousExams > 0 
        ? round((($exams - $previousExams) / $previousExams) * 100, 1) 
        : 0;

    // ✅ إضافة معلومات الفترة
    $periodLabels = [
        '7_days' => 'آخر 7 أيام',
        '30_days' => 'آخر شهر',
        '3_months' => 'آخر 3 شهور',
        '6_months' => 'آخر 6 شهور',
        '12_months' => 'آخر سنة',
    ];

    return [
        'period' => [
            'key' => $period,
            'label' => $periodLabels[$period] ?? 'آخر 7 أيام',
            'days' => $days,
        ],
        'assignments' => [
            'value' => number_format($assignments),
            'change' => $assignmentsChange,
            'previous' => $previousAssignments,
        ],
        'new_users' => [
            'value' => number_format($newUsers),
            'change' => $usersChange,
            'previous' => $previousUsers,
        ],
        'exams' => [
            'value' => number_format($exams),
            'change' => $examsChange,
            'previous' => $previousExams,
        ],
    ];
}

}
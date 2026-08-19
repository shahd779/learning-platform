<?php

namespace App\Http\Controllers\Api\Admin;

use Carbon\Carbon;
use App\Events\NewNotificationEvent;
use App\Exports\SubscriptionsExport;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\StudentSubscription;
use App\Models\Package;
use App\Models\User;
use App\Models\TeacherSubjectGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class SubscriptionController extends Controller
{
    /**
     * عرض كل الاشتراكات مع إحصائيات وفلترة
     */
    public function index(Request $request)
{
    // =============================================
    // 1. بناء الاستعلام الأساسي
    // =============================================
    $query = StudentSubscription::with([
        'student:id,name,phone',
        'package:id,name,price',
        'teacherSubjectGrade.subject:id,name',
        'teacherSubjectGrade.teacher:id,name',
        'teacherSubjectGrade.grade:id,name'
    ]);

    // =============================================
    // 2. الفلترة حسب الباقة
    // =============================================
    if ($request->has('package_id') && $request->package_id && $request->package_id !== 'all') {
        $query->where('package_id', $request->package_id);
    }

    // =============================================
    // 3. الفلترة حسب حالة الاشتراك
    // =============================================
    if ($request->has('status') && $request->status && $request->status !== 'all') {
        switch ($request->status) {
            case 'expiring_soon':
                // ✅ ينتهي قريباً: خلال 7 أيام
                $query->where('status', 'active')
                      ->whereNotNull('expires_at')
                      ->where('expires_at', '<=', now()->addDays(7));
                break;

            case 'active':
                // ✅ نشط: أكتر من 7 أيام أو expires_at = null
                $query->where('status', 'active')
                      ->where(function ($q) {
                          $q->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now()->addDays(7));
                      });
                break;

            case 'expired':
                // ✅ منتهي
                $query->where(function ($q) {
                    $q->where('status', 'expired')
                      ->orWhere('expires_at', '<=', now());
                });
                break;
        }
    }

    // =============================================
    // 4. البحث بالاسم أو رقم الهاتف
    // =============================================
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->whereHas('student', function ($q) use ($search) {
            $q->where('name', 'LIKE', '%' . $search . '%')
              ->orWhere('phone', 'LIKE', '%' . $search . '%');
        });
    }

    // =============================================
    // 5. الترتيب (الاحدث أولاً)
    // =============================================
    $query->orderBy('created_at', 'desc');

    // =============================================
    // 6. التصفح
    // =============================================
    $perPage = $request->per_page ?? 10;
    $subscriptions = $query->paginate($perPage);

    // =============================================
    // 7. تنسيق البيانات
    // =============================================
    $formattedSubscriptions = $subscriptions->through(function ($subscription) {
        return [
            'id' => $subscription->id,
            'student' => [
                'id' => $subscription->student->id ?? null,
                'name' => $subscription->student->name ?? null,
                'phone' => $subscription->student->phone ?? null,
            ],
            'package' => [
                'id' => $subscription->package->id ?? null,
                'name' => $subscription->package->name ?? null,
            ],
            'access_code' => $subscription->teacherSubjectGrade->access_code ?? null,
            'status' => $subscription->status,
            'status_label' => $this->getStatusLabel($subscription),
            'subscribed_at' => $subscription->subscribed_at ? $subscription->subscribed_at->format('Y-m-d H:i:s') : null,
            'expires_at' => $subscription->expires_at ? $subscription->expires_at->format('Y-m-d H:i:s') : null,
            'created_at' => $subscription->created_at->format('Y-m-d H:i:s'),
        ];
    });

    // =============================================
    // 8. الإحصائيات
    // =============================================
    $stats = $this->getStats();


    return response()->json([
        'success' => true,
        'stats' => $stats,
        'data' => $formattedSubscriptions,
        'pagination' => [
            'current_page' => $subscriptions->currentPage(),
            'per_page' => $subscriptions->perPage(),
            'total' => $subscriptions->total(),
            'last_page' => $subscriptions->lastPage(),
            'next_page_url' => $subscriptions->nextPageUrl(),
            'prev_page_url' => $subscriptions->previousPageUrl(),
        ]
    ]);
}

    /**
     * جلب الإحصائيات
     */
    private function getStats(): array
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $lastMonth = now()->subMonth()->month;
        $lastMonthYear = now()->subMonth()->year;

        // =============================================
        // 1. إجمالي المشتركين (كل الطلاب اللي اشتركوا)
        // =============================================
        $totalSubscribers = StudentSubscription::distinct('student_id')->count();

        // إجمالي المشتركين الشهر الماضي (للمقارنة)
        $totalSubscribersLastMonth = StudentSubscription::whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastMonthYear)
            ->distinct('student_id')
            ->count();

        $totalSubscribersChange = $this->calculatePercentageChange(
            $totalSubscribers,
            $totalSubscribersLastMonth
        );

        // =============================================
        // 2. الاشتراكات النشطة (لم تنتهي بعد)
        // =============================================
        $activeSubscriptions = StudentSubscription::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->count();

        // =============================================
        // 3. الاشتراكات المنتهية
        // =============================================
        $expiredSubscriptions = StudentSubscription::where('status', 'expired')
            ->orWhere('expires_at', '<=', now())
            ->count();

        // =============================================
        // 4. تجديدات هذا الشهر (اشتراكات جديدة لنفس المادة)
        // =============================================
        // جلب الاشتراكات اللي اتعملت هذا الشهر
        $renewalsThisMonth = StudentSubscription::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        // تجديدات الشهر الماضي (للمقارنة)
        $renewalsLastMonth = StudentSubscription::whereMonth('created_at', $lastMonth)
            ->whereYear('created_at', $lastMonthYear)
            ->count();

        $renewalsChange = $this->calculatePercentageChange(
            $renewalsThisMonth,
            $renewalsLastMonth
        );

        return [
            'total_subscribers' => $totalSubscribers,
            'total_subscribers_change' => $totalSubscribersChange,
            'active_subscriptions' => $activeSubscriptions,
            'expired_subscriptions' => $expiredSubscriptions,
            'renewals_this_month' => $renewalsThisMonth,
            'renewals_change' => $renewalsChange,
        ];
    }

    /**
     * جلب خيارات الفلترة
     */
    private function getFilterOptions(): array
    {
        return [
            'statuses' => [
                ['value' => 'all', 'label' => 'كل الحالات'],
                ['value' => 'active', 'label' => 'نشط'],
                ['value' => 'expired', 'label' => 'منتهي'],
                ['value' => 'expiring_soon', 'label' => 'ينتهي قريباً'],
            ],
            'packages' => Package::where('is_active', true)
                ->select('id', 'name')
                ->orderBy('price', 'asc')
                ->get(),
        ];
    }

    /**
     * جلب تسمية الحالة العربية
     */
    private function getStatusLabel($subscription): string
    {
        if ($subscription->status === 'expired') {
            return 'منتهي';
        }

        if ($subscription->expires_at && $subscription->expires_at <= now()->addDays(7)) {
            return 'ينتهي قريباً';
        }

        return 'نشط';
    }

    /**
     * حساب النسبة المئوية للتغيير
     */
    private function calculatePercentageChange($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * جلب خيارات الفلترة (API)
     */
    public function filterOptions()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getFilterOptions()
        ]);
    }

      public function export()
    {
        $fileName = 'المشتركين_' . now()->format('Y_m') . '.xlsx';

        // حفظ الملف في storage/app/public/exports/
        Excel::store(new SubscriptionsExport() , 'exports/' . $fileName, 'public');

        // رابط التحميل
        $fileUrl = url('/api/admin/download/' . urlencode($fileName));

        return response()->json([
            'success' => true,
            'message' => 'تم تصدير الملف بنجاح',
            'data' => [
                'file_name' => $fileName,
                'file_url' => $fileUrl,
                'expires_at' => now()->addHours(24),
            ]
        ]);
    }

    /**
     * تحميل الملف (تخدم الرابط)
     */
    public function downloadFile($fileName)
    {
        $filePath = storage_path('app/public/exports/' . $fileName);

        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'الملف غير موجود'
            ], 404);
        }

        // التحقق من صلاحية الملف (24 ساعة)
        $fileCreatedAt = filemtime($filePath);
        if (now()->timestamp - $fileCreatedAt > 86400) { // 24 ساعة
            // حذف الملف منتهي الصلاحية
            unlink($filePath);
            return response()->json([
                'success' => false,
                'message' => 'انتهت صلاحية رابط التحميل'
            ], 410);
        }

        return response()->download($filePath, $fileName);
    }
    /**
 * تفعيل اشتراك مجاني للطالب
 */
public function createFreeSubscription(Request $request)
{
    $validator = Validator::make($request->all(), [
        'student_id' => 'required|exists:users,id',
        'teacher_subject_grade_id' => 'required|exists:teacher_subject_grade,id',
        'package_id' => 'required|exists:packages,id',
        'subscribed_at' => 'required|date|after_or_equal:today',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $package = Package::find($request->package_id);
    
    // ✅ تاريخ البدء والانتهاء
    $subscribedAt = Carbon::parse($request->subscribed_at);
    $expiresAt = $subscribedAt->copy()->addDays($package->duration_days);

    // ✅ الشرط: التحقق من وجود اشتراك نشط في نفس الفترة وبنفس الباقة
    $existingSubscription = StudentSubscription::where('student_id', $request->student_id)
        ->where('teacher_subject_grade_id', $request->teacher_subject_grade_id)
        ->where('status', 'active')
        ->where('package_id', $request->package_id) // ✅ نفس الباقة
        ->where(function ($q) use ($subscribedAt, $expiresAt) {
            $q->whereBetween('subscribed_at', [$subscribedAt, $expiresAt])
              ->orWhereBetween('expires_at', [$subscribedAt, $expiresAt])
              ->orWhere(function ($q2) use ($subscribedAt, $expiresAt) {
                  $q2->where('subscribed_at', '<=', $subscribedAt)
                     ->where('expires_at', '>=', $expiresAt);
              });
        })
        ->exists();

    if ($existingSubscription) {
        return response()->json([
            'success' => false,
            'message' => 'يوجد اشتراك نشط للطالب في هذه المادة بنفس الباقة خلال الفترة المحددة'
        ], 422);
    }

    // ✅ إنشاء الاشتراك
    $subscription = StudentSubscription::create([
        'student_id' => $request->student_id,
        'teacher_subject_grade_id' => $request->teacher_subject_grade_id,
        'package_id' => $request->package_id,
        'is_free' => true,
        'status' => 'active',
        'subscribed_at' => $subscribedAt,
        'expires_at' => $expiresAt,
    ]);

    // إشعار للطالب
    $this->notifyStudentFree($subscription);

    return response()->json([
        'success' => true,
        'message' => 'تم تفعيل الاشتراك المجاني بنجاح',
        'data' => $subscription->load(['student', 'package'])
    ]);
}

/**
 * إرسال إشعار للطالب بالاشتراك المجاني
 */
private function notifyStudentFree($subscription)
{
    $notification = Notification::create([
        'user_id' => $subscription->student_id,
        'triggered_by_id' => auth()->id(),
        'type' => 'subscription_free',
        'message' => " تم تفعيل اشتراك مجاني لك في مادة: {$subscription->teacherSubjectGrade->subject->name}",
        'data' => [
            'subscription_id' => $subscription->id,
            'package_name' => $subscription->package->name,
            'subject_name' => $subscription->teacherSubjectGrade->subject->name,
            'expires_at' => $subscription->expires_at,
        ],
        'is_read' => false,
    ]);

    try {
        broadcast(new NewNotificationEvent($notification));
    } catch (\Exception $e) {
        // لو الـ broadcasting مش شغال
    }
}
/**
 * جلب البيانات للـ Dropdown (الطلاب - المواد - الباقات)
 */
public function getFormData()
{
    return response()->json([
        'success' => true,
        'data' => [
            'students' => User::where('role', 'student')
                ->where('is_active', true)
                ->select('id', 'name', 'phone')
                ->orderBy('name')
                ->get(),
            
            'packages' => Package::where('is_active', true)
                ->select('id', 'name')
                ->orderBy('price', 'asc')
                ->get(),
            
            'subjects' => TeacherSubjectGrade::with(['subject', 'teacher', 'grade'])
                ->where('is_active', true)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'subject_name' => $item->subject->name ?? null,
                        'teacher_name' => $item->teacher->name ?? null,
                        'grade_name' => $item->grade->name ?? null,
                        'access_code' => $item->access_code,
                    ];
                }),
        ]
    ]);
}

}
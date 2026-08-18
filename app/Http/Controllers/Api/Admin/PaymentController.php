<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\User;
use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;
use App\Models\Package;
use App\Models\Notification;
use App\Events\NewNotificationEvent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * عرض كل طلبات الدفع مع إحصائيات وفلترة
     */
    public function index(Request $request)
{
    // =============================================
    // 1. بناء الاستعلام الأساسي
    // =============================================
    $query = Payment::with([
        'student:id,name,phone',
        'teacherSubjectGrade.subject:id,name',
        'teacherSubjectGrade.teacher:id,name',
        'teacherSubjectGrade.grade:id,name',
        'reviewer:id,name'
    ]);

    // =============================================
    // 2. الفلترة حسب الحالة
    // =============================================
    if ($request->has('status') && $request->status && $request->status !== 'all') {
        $query->where('status', $request->status);
    }

    // =============================================
    // 3. البحث بالاسم أو رقم الهاتف
    // =============================================
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->whereHas('student', function ($q2) use ($search) {
                $q2->where('name', 'LIKE', '%' . $search . '%')
                   ->orWhere('phone', 'LIKE', '%' . $search . '%');
            })
            ->orWhere('from_phone', 'LIKE', '%' . $search . '%')
            ->orWhere('transaction_id', 'LIKE', '%' . $search . '%');
        });
    }

    // =============================================
    // 4. الفلترة حسب التاريخ (date_filter)
    // =============================================
    if ($request->has('date_filter') && $request->date_filter) {
        $dateRange = $this->getDateRange($request->date_filter);
        if ($dateRange) {
            $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
        }
    }

    // =============================================
    // 5. الفلترة حسب الفترة الزمنية (period)
    // =============================================
    if ($request->has('period') && $request->period) {
        $periodRange = $this->getDateRange($request->period);
        if ($periodRange) {
            $query->whereBetween('created_at', [$periodRange['start'], $periodRange['end']]);
        }
    }

    // =============================================
    // ✅ 6. الفلترة الافتراضية (هذا الشهر)
    // =============================================
    // لو مفيش date_filter و مفيش period و مفيش search، نطبق هذا الشهر
    $hasDateFilter = $request->has('date_filter') && $request->date_filter;
    $hasPeriod = $request->has('period') && $request->period;
    $hasSearch = $request->has('search') && $request->search;

    if (!$hasDateFilter && !$hasPeriod && !$hasSearch) {
        $query->whereMonth('created_at', now()->month)
              ->whereYear('created_at', now()->year);
    }

    // =============================================
    // 7. الترتيب والتصفح
    // =============================================
    $query->orderBy('created_at', 'desc');
    $perPage = $request->per_page ?? 10;
    $payments = $query->paginate($perPage);

    // =============================================
    // 8. تنسيق البيانات
    // =============================================
    $formattedPayments = $payments->through(function ($payment) {
        return [
            'id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'student' => [
                'id' => $payment->student->id ?? null,
                'name' => $payment->student->name ?? null,
                'phone' => $payment->student->phone ?? null,
            ],
            'from_phone' => $payment->from_phone,
            'access_code' => $payment->teacherSubjectGrade->access_code ?? null,
            'transfer_image' => $payment->transfer_image ? asset('storage/' . $payment->transfer_image) : null,
            'status' => $payment->status,
            'status_label' => $this->getStatusLabel($payment->status),
            'reviewer' => $payment->reviewer ? [
                'id' => $payment->reviewer->id,
                'name' => $payment->reviewer->name,
            ] : null,
            'reviewed_at' => $payment->reviewed_at ? $payment->reviewed_at->format('Y-m-d H:i:s') : null,
            'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
        ];
    });

    // =============================================
    // 9. الإحصائيات
    // =============================================
    $stats = $this->getStats($request);

    return response()->json([
        'success' => true,
        'stats' => $stats,
        'data' => $formattedPayments,
        'pagination' => [
            'current_page' => $payments->currentPage(),
            'per_page' => $payments->perPage(),
            'total' => $payments->total(),
            'last_page' => $payments->lastPage(),
            'next_page_url' => $payments->nextPageUrl(),
            'prev_page_url' => $payments->previousPageUrl(),
        ]
    ]);
}


    /**
     * جلب خيارات الفلترة
     */
    private function getFilterOptions()
    {
        return [
            'statuses' => [
                ['value' => 'all', 'label' => 'كل الحالات'],
                ['value' => 'pending', 'label' => 'بانتظار المراجعة'],
                ['value' => 'approved', 'label' => 'تمت الموافقة'],
                ['value' => 'rejected', 'label' => 'تم الرفض'],
            ],
            'date_filters' => [
                ['value' => 'today', 'label' => 'اليوم'],
                ['value' => 'this_week', 'label' => 'هذا الأسبوع'],
                ['value' => 'this_month', 'label' => 'هذا الشهر'],
            ],
            'periods' => [
                ['value' => 'this_month', 'label' => 'هذا الشهر'],
                ['value' => 'last_month', 'label' => 'الشهر الماضي'],
                ['value' => 'last_6_months', 'label' => 'آخر 6 شهور'],
                ['value' => 'last_year', 'label' => 'آخر سنة'],
                ['value' => 'all', 'label' => 'كل الأوقات'],
            ],
        ];
    }

    public function filterOptions()
    {
        return response()->json([
            'success' => true,
            'data' => $this->getFilterOptions(),
        ]);
    }


    /**
     * جلب الإحصائيات
     */
    private function getStats($request)
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        // كل الطلبات في كل الأوقات
    
        $pendingAll = Payment::where('status', 'pending')->count();

        // طلبات الشهر الحالي
        $totalThisMonth = Payment::whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        $approvedThisMonth = Payment::where('status', 'approved')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        $rejectedThisMonth = Payment::where('status', 'rejected')
            ->whereMonth('created_at', $currentMonth)
            ->whereYear('created_at', $currentYear)
            ->count();

        return [
            'total_this_month' => $totalThisMonth,
            'pending_all' => $pendingAll,
            'approved_this_month' => $approvedThisMonth,
            'rejected_this_month' => $rejectedThisMonth,
        ];
    }

    /**
     * جلب نطاق التاريخ
     */
    private function getDateRange($filter)
    {
        $now = now();

        switch ($filter) {
            case 'today':
                return [
                    'start' => $now->copy()->startOfDay(),
                    'end' => $now->copy()->endOfDay(),
                ];

            case 'this_week':
                return [
                    'start' => $now->copy()->startOfWeek(),
                    'end' => $now->copy()->endOfWeek(),
                ];

            case 'this_month':
                return [
                    'start' => $now->copy()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];

            case 'last_month':
                return [
                    'start' => $now->copy()->subMonth()->startOfMonth(),
                    'end' => $now->copy()->subMonth()->endOfMonth(),
                ];

            case 'last_6_months':
                return [
                    'start' => $now->copy()->subMonths(6)->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];

            case 'last_year':
                return [
                    'start' => $now->copy()->subYear()->startOfMonth(),
                    'end' => $now->copy()->endOfMonth(),
                ];

            case 'all':
            default:
                return null;
        }
    }

    /**
     * جلب التسمية العربية للحالة
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'pending' => 'بانتظار المراجعة',
            'approved' => 'تمت الموافقة',
            'rejected' => 'تم الرفض',
        ];

        return $labels[$status] ?? $status;
    }


  
}
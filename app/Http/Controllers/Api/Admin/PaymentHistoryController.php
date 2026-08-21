<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\StudentSubscription;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentHistoryController extends Controller
{
/**
 * عرض سجل المدفوعات مع إحصائيات وفلترة
 */
public function index(Request $request)
{
    // =============================================
    // 1. بناء الاستعلام الأساسي
    // =============================================
    $query = Payment::with([
        'student:id,name,phone',
        'subscription.package:id,name,price',
        'teacherSubjectGrade.subject:id,name',
        'teacherSubjectGrade.teacher:id,name',
        'teacherSubjectGrade.grade:id,name',
        'reviewer:id,name'
    ])->where('status', 'approved');

    // =============================================
    // 2. فلترة البيانات حسب start_date و end_date فقط
    // =============================================
    if ($request->has('start_date') && $request->has('end_date')) {
        $query->whereBetween('created_at', [
            Carbon::parse($request->start_date)->startOfDay(),
            Carbon::parse($request->end_date)->endOfDay()
        ]);
    }

    // =============================================
    // 3. فلترة حسب الباقة (من خلال الاشتراك)
    // =============================================
    if ($request->has('package_id') && $request->package_id && $request->package_id !== 'all') {
        $query->whereHas('subscription', function ($q) use ($request) {
            $q->where('package_id', $request->package_id);
        });
    }

    // =============================================
    // 4. البحث بالاسم أو رقم الهاتف أو رقم العملية
    // =============================================
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('transaction_id', 'LIKE', '%' . $search . '%')
              ->orWhereHas('student', function ($q2) use ($search) {
                  $q2->where('name', 'LIKE', '%' . $search . '%')
                     ->orWhere('phone', 'LIKE', '%' . $search . '%');
              });
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
    $payments = $query->paginate($perPage);

    // =============================================
    // 7. تنسيق البيانات
    // =============================================
    $formattedPayments = $payments->through(function ($payment) {
        $package = $payment->subscription->package ?? null;
        
        return [
            'id' => $payment->id,
            'transaction_id' => $payment->transaction_id,
            'student' => [
                'id' => $payment->student->id ?? null,
                'name' => $payment->student->name ?? null,
                'phone' => $payment->student->phone ?? null,
            ],
            'package' => [
                'id' => $package->id ?? null,
                'name' => $package->name ?? null,
                'price' => $package->price ?? 0,
            ],
            'subject' => [
                'id' => $payment->teacherSubjectGrade->subject->id ?? null,
                'name' => $payment->teacherSubjectGrade->subject->name ?? null,
            ],
            'grade' => [
                'id' => $payment->teacherSubjectGrade->grade->id ?? null,
                'name' => $payment->teacherSubjectGrade->grade->name ?? null,
            ],
            'access_code' => $payment->teacherSubjectGrade->access_code ?? null,
            'amount' => $package->price ?? 0,
            'status' => $payment->status,
            'status_label' => $this->getStatusLabel($payment->status),
            'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
            'reviewed_at' => $payment->reviewed_at ? $payment->reviewed_at->format('Y-m-d H:i:s') : null,
        ];
    });

    // =============================================
    // 8. الإحصائيات (بتتأثر بـ period فقط)
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
     * جلب الإحصائيات
     */
    private function getStats($request)
{
    // ✅ تحديد الفترة
    $period = $request->period ?? 'this_month';
    
    // ✅ لو all، خلي الفترة كل الأوقات
    if ($period === 'all') {
        $firstPayment = Payment::min('created_at');
        $currentStart = $firstPayment ? Carbon::parse($firstPayment) : now()->subMonth();
        $currentEnd = now();
    } else {
        $dateRange = $this->getDateRange($period);
        $currentStart = $dateRange['start'];
        $currentEnd = $dateRange['end'];
    }

    // ✅ الفترة السابقة للمقارنة
    if ($currentStart && $currentEnd) {
        $diffDays = $currentStart->diffInDays($currentEnd) + 1;
        $previousStart = $currentStart->copy()->subDays($diffDays);
        $previousEnd = $currentEnd->copy()->subDays($diffDays);
    } else {
        $previousStart = null;
        $previousEnd = null;
    }

    // =============================================
    // 1. إجمالي الإيرادات
    // =============================================
    $currentRevenue = $this->getRevenue($currentStart, $currentEnd);
    $previousRevenue = $this->getRevenue($previousStart, $previousEnd);
    $revenueChange = $this->calculatePercentageChange($currentRevenue, $previousRevenue);

    // =============================================
    // 2. عدد المدفوعات
    // =============================================
    $currentCount = $this->getPaymentsCount($currentStart, $currentEnd);
    $previousCount = $this->getPaymentsCount($previousStart, $previousEnd);
    $countChange = $this->calculatePercentageChange($currentCount, $previousCount);

    return [
        'total_revenue' => $currentRevenue,
        'total_revenue_change' => $revenueChange,
        'payments_count' => $currentCount,
        'payments_count_change' => $countChange,
        'period' => [
            'start' => $currentStart ? $currentStart->format('Y-m-d') : 'من أول المنصة',
            'end' => $currentEnd ? $currentEnd->format('Y-m-d') : 'الآن',
        ]
    ];
}

    /**
     * ✅ جلب إجمالي الإيرادات في فترة معينة (من الاشتراكات)
     */
    private function getRevenue($start, $end): float
    {
        // جلب المدفوعات المقبولة مع اشتراكاتها
        $payments = Payment::where('status', 'approved')
            ->whereBetween('created_at', [$start, $end])
            ->with('subscription.package') // ✅ جلب الاشتراك مع الباقة
            ->get();

        return $payments->sum(function ($payment) {
            // جلب سعر الباقة من الاشتراك
            return $payment->subscription->package->price ?? 0;
        });
    }

    /**
     * ✅ عدد المدفوعات في فترة معينة
     */
    private function getPaymentsCount($start, $end): int
    {
        return Payment::where('status', 'approved')
            ->whereBetween('created_at', [$start, $end])
            ->count();
    }

    /**
     * جلب نطاق التاريخ
     */
    private function getDateRange($filter)
{
    $now = now();

    switch ($filter) {
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
            return [
                'start' => null,
                'end' => null,
            ];
    }
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

    /**
     * جلب خيارات الفلترة (API)
     */
    public function filterOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'periods' => [
                    ['value' => 'this_month', 'label' => 'هذا الشهر'],
                    ['value' => 'last_month', 'label' => 'الشهر الماضي'],
                    ['value' => 'last_6_months', 'label' => 'آخر 6 شهور'],
                    ['value' => 'last_year', 'label' => 'آخر سنة'],
                    ['value' => 'all', 'label' => 'كل الفترة'],
                ],
                'packages' => Package::where('is_active', true)
                    ->select('id', 'name', 'price')
                    ->orderBy('price', 'asc')
                    ->get(),
            ]
        ]);
    }
}
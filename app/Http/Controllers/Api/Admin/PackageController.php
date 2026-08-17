<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\StudentSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    /**
 * جلب خيارات الفلترة للباقات
 */
public function filterOptions()
{
    return response()->json([
        'success' => true,
        'data' => [
            'filters' => [
                [
                    'value' => 'all',
                    'label' => 'كل الأوقات',
                ],
                [
                    'value' => 'this_month',
                    'label' => 'هذا الشهر',
                ],
                [
                    'value' => 'last_month',
                    'label' => 'الشهر الماضي',
                ],
                [
                    'value' => 'last_6_months',
                    'label' => 'آخر 6 شهور',
                ],
                [
                    'value' => 'last_year',
                    'label' => 'آخر سنة',
                ],
            ]
        ]
    ]);
}

    public function index(Request $request)
    {
        $packages = Package::orderBy('price', 'asc')->get();

        $filter = $request->filter ?? 'all'; 

        $dateRange = $this->getDateRange($filter);

        $stats = $this->getStats($dateRange);

        return response()->json([
            'success' => true,
            'data' => [
                'packages' => $packages,
                'stats' => $stats,
                'filter' => $filter,
            ]
        ]);
    }

    private function getDateRange(string $filter): array
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
 * جلب الإحصائيات
 */
private function getStats(array $dateRange): array
{
    $currentStart = $dateRange['start'];
    $currentEnd = $dateRange['end'];

    // =============================================
    // الفترة السابقة (للمقارنة)
    // =============================================
    if ($currentStart && $currentEnd) {
        $diffDays = $currentStart->diffInDays($currentEnd) + 1;
        $previousStart = $currentStart->copy()->subDays($diffDays);
        $previousEnd = $currentEnd->copy()->subDays($diffDays);
    } else {
        $previousStart = null;
        $previousEnd = now()->copy()->subMonth()->endOfMonth();
    }
    $totalPackages = Package::count();

    
    // 2. المشتركين التراكمي (من أول المنصة لحد نهاية الفترة)
    $totalSubscribersCurrent = $this->getTotalSubscribersCount($currentEnd);
    $totalSubscribersPrevious = $this->getTotalSubscribersCount($previousEnd);

    $totalSubscribersChange = $this->calculatePercentageChange(
        $totalSubscribersCurrent,
        $totalSubscribersPrevious
    );

    // 5. المشتركين الجدد (أول مرة في الفترة)
    $newSubscribersCurrent = $this->getNewSubscribersCount($currentStart, $currentEnd);
    $newSubscribersPrevious = $this->getNewSubscribersCount($previousStart, $previousEnd);

    $newSubscribersChange = $this->calculatePercentageChange(
        $newSubscribersCurrent,
        $newSubscribersPrevious
    );
    // 6. الإيرادات
    $revenueCurrent = $this->getRevenue($currentStart, $currentEnd);
    $revenuePrevious = $this->getRevenue($previousStart, $previousEnd);

    $revenueChange = $this->calculatePercentageChange(
        $revenueCurrent,
        $revenuePrevious
    );

    return [
        'total_packages' => $totalPackages,
        
        // المشتركين التراكمي (من أول المنصة)
        'total_subscribers' => $totalSubscribersCurrent,
        'total_subscribers_change' => $totalSubscribersChange,
 
        
        // المشتركين الجدد
        'new_subscribers' => $newSubscribersCurrent,
        'new_subscribers_change' => $newSubscribersChange,
        
        // الإيرادات
        'total_revenue' => $revenueCurrent,
        'total_revenue_change' => $revenueChange,
    ];
}

/**
 * جلب عدد المشتركين التراكمي (من أول المنصة لحد تاريخ معين)
 */
private function getTotalSubscribersCount($endDate): int
{
    $query = StudentSubscription::distinct('student_id');

    if ($endDate) {
        $query->where('created_at', '<=', $endDate);
    }

    return $query->count('student_id');
}

    /**
     * جلب عدد المشتركين الجدد (أول مرة)
     */
    private function getNewSubscribersCount($start, $end): int
    {
        $query = StudentSubscription::select('student_id', DB::raw('MIN(created_at) as first_subscription'))
            ->groupBy('student_id');

        if ($start && $end) {
            $query->havingBetween('first_subscription', [$start, $end]);
        }

        return $query->get()->count();
    }

    /**
     * جلب إجمالي الإيرادات
     */
    private function getRevenue($start, $end): float
    {
        $query = Payment::where('status', 'approved');

        if ($start && $end) {
            $query->whereBetween('created_at', [$start, $end]);
        }

        return $query->sum('amount') ?? 0;
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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:packages,name',
            'price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'duration_days' => 'required|integer|min:1|max:365',
            'features' => 'required|array|min:1',
            'features.*' => 'string|max:255',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required' => 'اسم الباقة مطلوب',
            'name.unique' => 'هذا الاسم موجود بالفعل',
            'price.required' => 'سعر الباقة مطلوب',
            'price.numeric' => 'السعر يجب أن يكون رقماً',
            'price.min' => 'السعر لا يمكن أن يكون سالباً',
            'duration_days.required' => 'مدة الباقة بالأيام مطلوبة',
            'duration_days.min' => 'مدة الباقة يجب أن تكون يوم على الأقل',
            'duration_days.max' => 'مدة الباقة لا تتجاوز 365 يوم',
            'features.required' => 'المميزات مطلوبة',
            'features.min' => 'يجب إضافة ميزة واحدة على الأقل',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        $package = Package::create([
            'name' => $request->name,
            'price' => $request->price,
            'duration_days' => $request->duration_days,
            'features' => $request->features,
            'is_active' => $request->is_active ?? true,
        ]);
        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الباقة بنجاح',
            'data' => $package
        ], 201);
    }


    public function update(Request $request, $id)
{
    $package = Package::find($id);

    if (!$package) {
        return response()->json([
            'success' => false,
            'message' => 'الباقة غير موجودة'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|string|max:255|unique:packages,name,' . $id,
        'price' => 'sometimes|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
        'duration_days' => 'sometimes|integer|min:1|max:365',
        'features' => 'nullable|array',
        'features.*' => 'string|max:255',
        'is_active' => 'nullable|boolean',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $data = $request->only(['name', 'price', 'duration_days', 'is_active']);

    // معالجة المميزات - التعديل هنا
    if ($request->has('features')) {
        $data['features'] = $request->features; // هتكون array أو null
    }

    $package->update($data);

    return response()->json([
        'success' => true,
        'message' => 'تم تحديث الباقة بنجاح',
        'data' => $package
    ]);
}


    public function destroy($id)
    {
        $package = Package::find($id);

        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'الباقة غير موجودة'
            ], 404);
        }

        // التحقق من وجود مشتركين في هذه الباقة
        if ($package->subscriptions()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف الباقة لأن هناك مشتركين فيها'
            ], 422);
        }

        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الباقة بنجاح'
        ]);
    }
    
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;

class UserController extends Controller
{
    /**
     * إحصائيات المستخدمين
     */
    public function stats()
    {
        $totalUsers = User::count();
        $admins = User::where('role', 'admin')->count();
        $teachers = User::where('role', 'teacher')->count();
        $students = User::where('role', 'student')->count();
        $blockedUsers = User::where('is_active', false)->count();

        // المدرسين النشطين (اللي عندهم مواد)
        $activeTeachers = TeacherSubjectGrade::distinct('teacher_id')->count();

        // الطلاب النشطين (اللي عندهم اشتراكات نشطة)
        $activeStudents = StudentSubscription::where('status', 'active')
            ->distinct('student_id')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => $totalUsers,
                'admins' => $admins,
                'teachers' => $teachers,
                'students' => $students,
                'blocked_users' => $blockedUsers,
            ]
        ]);
    }

    /**
     * عرض كل المستخدمين مع فلترة وبحث
     */
public function index(Request $request)
{
    $query = User::query();

    // ✅ إضافة فلترة تلقائية: عرض الحسابات النشطة فقط (ما لم يتم طلب غير ذلك)
    $showBlocked = $request->has('show_blocked') && $request->show_blocked === 'true';
    
    if (!$showBlocked) {
        $query->where('is_active', true);
    }

    // فلترة حسب الدور
    if ($request->has('role') && $request->role && $request->role !== 'all') {
        $query->where('role', $request->role);
    }

    // ✅ فلترة حسب حالة اشتراك الطالب (تم التعديل)
    if ($request->has('subscription_status') && $request->subscription_status !== 'all') {
        $subscriptionStatus = $request->subscription_status;
        
        $query->whereHas('studentSubscriptions', function ($q) use ($subscriptionStatus) {
            $q->where('status', $subscriptionStatus);
        });
    }

    // ✅ فلترة حسب تاريخ الاشتراك (تم التعديل)
    if ($request->has('subscribed_from') && $request->subscribed_from) {
        $query->whereHas('studentSubscriptions', function ($q) use ($request) {
            $q->whereDate('subscribed_at', '>=', $request->subscribed_from);
        });
    }

    if ($request->has('subscribed_to') && $request->subscribed_to) {
        $query->whereHas('studentSubscriptions', function ($q) use ($request) {
            $q->whereDate('subscribed_at', '<=', $request->subscribed_to);
        });
    }

    // ✅ فلترة حسب تاريخ انتهاء الاشتراك (تم التعديل)
    if ($request->has('expires_from') && $request->expires_from) {
        $query->whereHas('studentSubscriptions', function ($q) use ($request) {
            $q->whereDate('expires_at', '>=', $request->expires_from);
        });
    }

    if ($request->has('expires_to') && $request->expires_to) {
        $query->whereHas('studentSubscriptions', function ($q) use ($request) {
            $q->whereDate('expires_at', '<=', $request->expires_to);
        });
    }

    // بحث بالاسم أو رقم الهاتف
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', '%' . $search . '%')
              ->orWhere('phone', 'LIKE', '%' . $search . '%');
        });
    }

    // ترتيب
    $sortField = $request->sort_by ?? 'created_at';
    $sortDirection = $request->sort_direction ?? 'desc';
    $query->orderBy($sortField, $sortDirection);

    $perPage = $request->per_page ?? 10;
    $users = $query->paginate($perPage);

    // إضافة بيانات مختلفة حسب الدور
    $users->getCollection()->transform(function ($user) {
        
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'image' => $user->image,
            'image_url' => $user->image_url,
        ];

        if ($user->role === 'teacher') {
            $subjects = TeacherSubjectGrade::where('teacher_id', $user->id)
                ->with(['subject', 'grade'])
                ->get();
            
            $userData['subjects'] = $subjects->map(function($item) {
                return [
                    'subject' => $item->subject->name,
                ];
            });
            $userData['start_date'] = $user->created_at->format('Y-m-d');
        }

        if ($user->role === 'student') {
            $subscriptions = StudentSubscription::where('student_id', $user->id)
                ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade', 'teacherSubjectGrade.teacher'])
                ->get();
            
            $userData['subscriptions'] = $subscriptions->map(function($subscription) {
                return [
                    'id' => $subscription->id,
                    'subject' => $subscription->teacherSubjectGrade->subject->name,
                    'grade' => $subscription->teacherSubjectGrade->grade->name,
                    'teacher' => $subscription->teacherSubjectGrade->teacher->name,
                    'access_code' => $subscription->teacherSubjectGrade->access_code,
                    'status' => $subscription->status,
                    'subscribed_at' => $subscription->subscribed_at ? $subscription->subscribed_at->format('Y-m-d') : null,
                    'expires_at' => $subscription->expires_at ? $subscription->expires_at->format('Y-m-d') : null,
                ];
            });
            
            $lastActive = $subscriptions->where('status', 'active')->first();
            if ($lastActive) {
                $userData['last_subscription'] = [
                    'subject' => $lastActive->teacherSubjectGrade->subject->name,
                    'grade' => $lastActive->teacherSubjectGrade->grade->name,
                    'teacher' => $lastActive->teacherSubjectGrade->teacher->name,
                    'access_code' => $lastActive->teacherSubjectGrade->access_code,
                    'status' => $lastActive->status,
                    'subscribed_at' => $lastActive->subscribed_at ? $lastActive->subscribed_at->format('Y-m-d') : null,
                    'expires_at' => $lastActive->expires_at ? $lastActive->expires_at->format('Y-m-d') : null,
                ];
            }
        }

        if ($user->role === 'admin') {
            $userData['start_date'] = $user->created_at->format('Y-m-d');
        }

        return $userData;
    });

    return response()->json([
        'success' => true,
        'data' => $users,
        'filters' => [
            'role' => $request->role,
            'subscription_status' => $request->subscription_status,
            'search' => $request->search,
            'subscribed_from' => $request->subscribed_from,
            'subscribed_to' => $request->subscribed_to,
            'expires_from' => $request->expires_from,
            'expires_to' => $request->expires_to,
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}

/**
 * جلب الحسابات المتوقفة فقط
 */
public function blockedUsers(Request $request)
{
    $query = User::where('is_active', false);

    // فلترة حسب الدور
    if ($request->has('role') && $request->role && $request->role !== 'all') {
        $query->where('role', $request->role);
    }

    // ✅ فلترة حسب حالة اشتراك الطالب (تم التعديل)
    if ($request->has('subscription_status') && $request->subscription_status !== 'all') {
        $subscriptionStatus = $request->subscription_status;
        
        $query->whereHas('studentSubscriptions', function ($q) use ($subscriptionStatus) {
            $q->where('status', $subscriptionStatus);
        });
    }

    // ✅ فلترة حسب تاريخ الاشتراك (تم التعديل)
    if ($request->has('subscribed_from') && $request->subscribed_from) {
        $query->whereHas('studentSubscriptions', function ($q) use ($request) {
            $q->whereDate('subscribed_at', '>=', $request->subscribed_from);
        });
    }

    if ($request->has('subscribed_to') && $request->subscribed_to) {
        $query->whereHas('studentSubscriptions', function ($q) use ($request) {
            $q->whereDate('subscribed_at', '<=', $request->subscribed_to);
        });
    }

    // ✅ فلترة حسب تاريخ انتهاء الاشتراك (تم التعديل)
    if ($request->has('expires_from') && $request->expires_from) {
        $query->whereHas('studentSubscriptions', function ($q) use ($request) {
            $q->whereDate('expires_at', '>=', $request->expires_from);
        });
    }

    if ($request->has('expires_to') && $request->expires_to) {
        $query->whereHas('studentSubscriptions', function ($q) use ($request) {
            $q->whereDate('expires_at', '<=', $request->expires_to);
        });
    }

    // بحث
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', '%' . $search . '%')
              ->orWhere('phone', 'LIKE', '%' . $search . '%');
        });
    }

    // ترتيب
    $sortField = $request->sort_by ?? 'created_at';
    $sortDirection = $request->sort_direction ?? 'desc';
    $query->orderBy($sortField, $sortDirection);

    $perPage = $request->per_page ?? 10;
    $users = $query->paginate($perPage);

    // إضافة نفس البيانات التفصيلية
    $users->getCollection()->transform(function ($user) {
        
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'image' => $user->image,
            'image_url' => $user->image_url,
        ];

        if ($user->role === 'teacher') {
            $subjects = TeacherSubjectGrade::where('teacher_id', $user->id)
                ->with(['subject', 'grade'])
                ->get();
            
            $userData['subjects'] = $subjects->map(function($item) {
                return [
                    'subject' => $item->subject->name,
                ];
            });
            $userData['start_date'] = $user->created_at->format('Y-m-d');
        }

        if ($user->role === 'student') {
            $subscriptions = StudentSubscription::where('student_id', $user->id)
                ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade', 'teacherSubjectGrade.teacher'])
                ->get();
            
            $userData['subscriptions'] = $subscriptions->map(function($subscription) {
                return [
                    'id' => $subscription->id,
                    'subject' => $subscription->teacherSubjectGrade->subject->name,
                    'status' => $subscription->status,
                    'subscribed_at' => $subscription->subscribed_at ? $subscription->subscribed_at->format('Y-m-d') : null,
                    'expires_at' => $subscription->expires_at ? $subscription->expires_at->format('Y-m-d') : null,
                ];
            });
            
            $lastSubscription = $subscriptions->first();
            if ($lastSubscription) {
                $userData['last_subscription'] = [
                    'subject' => $lastSubscription->teacherSubjectGrade->subject->name,
                    'status' => $lastSubscription->status,
                    'subscribed_at' => $lastSubscription->subscribed_at ? $lastSubscription->subscribed_at->format('Y-m-d') : null,
                    'expires_at' => $lastSubscription->expires_at ? $lastSubscription->expires_at->format('Y-m-d') : null,
                ];
            }
        }

        if ($user->role === 'admin') {
            $userData['start_date'] = $user->created_at->format('Y-m-d');
        }

        return $userData;
    });

    return response()->json([
        'success' => true,
        'data' => $users,
        'filters' => [
            'role' => $request->role,
            'search' => $request->search,
            'subscription_status' => $request->subscription_status,
            'subscribed_from' => $request->subscribed_from,
            'subscribed_to' => $request->subscribed_to,
            'expires_from' => $request->expires_from,
            'expires_to' => $request->expires_to,
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}
    /**
     * جلب خيارات الفلترة
     */
    public function filterOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'roles' => [
                    ['value' => 'all', 'label' => 'الكل'],
                    ['value' => 'admin', 'label' => 'مديرين'],
                    ['value' => 'teacher', 'label' => 'مدرسين'],
                    ['value' => 'student', 'label' => 'طلاب'],
                ],
                'statuses' => [
                    ['value' => 'all', 'label' => 'الكل'],
                    ['value' => 'active', 'label' => 'نشط'],
                    ['value' => 'blocked', 'label' => 'موقوف'],
                ],
            ]
        ]);
    }

    /**
     * إضافة مستخدم جديد (طالب أو مدرس)
     */
public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'phone' => 'required|string|unique:users,phone|regex:/^[0-9]{11}$/',
        'password' => 'required|string|min:6',
        'role' => 'required|in:student,teacher',
        'is_active' => 'boolean',
        // ✅ access_code مطلوب للطلاب
        'access_code' => 'required_if:role,student|exists:teacher_subject_grade,access_code',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // إنشاء المستخدم
    $user = User::create([
        'name' => $request->name,
        'phone' => $request->phone,
        'password' => Hash::make($request->password),
        'role' => $request->role,
        'is_active' => $request->is_active ?? true,
    ]);

    // ✅ لو طالب ومدخل كود
    if ($request->role === 'student' && $request->has('access_code')) {
        $teacherSubjectGrade = TeacherSubjectGrade::where('access_code', $request->access_code)->first();
        
        if ($teacherSubjectGrade) {
            // التحقق من عدم وجود اشتراك مسبق
            $existingSubscription = StudentSubscription::where([
                'student_id' => $user->id,
                'teacher_subject_grade_id' => $teacherSubjectGrade->id,
            ])->first();

            if (!$existingSubscription) {
                // ✅ إنشاء اشتراك جديد
                StudentSubscription::create([
                    'student_id' => $user->id,
                    'teacher_subject_grade_id' => $teacherSubjectGrade->id,
                    'status' => 'active',
                    'subscribed_at' => now(),
                    'expires_at' => null, // مش مدفوع حالياً
                ]);
            }
        }
    }

    $message = $request->role === 'teacher' 
        ? 'تم إضافة المدرس بنجاح. يمكنه تسجيل الدخول وطلب إضافة مادة.'
        : 'تم إضافة الطالب بنجاح وتم تسجيله في المادة بنجاح.';

    return response()->json([
        'success' => true,
        'message' => $message,
        'data' => [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
            'login_info' => [
                'phone' => $user->phone,
                'password' => $request->password,
            ],
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}
    /**
     * عرض مستخدم معين
     */
    public function show($id)
    {
        $user = User::with(['teacherSubjects', 'subscriptions'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * تحديث مستخدم
     */
public function update(Request $request, $id)
{
    $user = User::find($id);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'المستخدم غير موجود'
        ], 404);
    }

    $validator = Validator::make($request->all(), [
        'name' => 'sometimes|string|max:255',
        'phone' => 'sometimes|string|unique:users,phone,' . $id . '|regex:/^[0-9]{11}$/',
        'role' => 'sometimes|in:student,teacher',
        'is_active' => 'sometimes|boolean',
        'password' => 'sometimes|string|min:6',
        // ✅ إضافة كود المادة (لطلاب فقط)
        'access_code' => 'sometimes|required_if:role,student|exists:teacher_subject_grade,access_code',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $data = $request->only(['name', 'phone', 'role', 'is_active']);
    
    // تحديث كلمة المرور لو موجودة
    if ($request->has('password') && $request->password) {
        $data['password'] = Hash::make($request->password);
    }

    $user->update($data);

    // ✅ لو المستخدم طالب وتم إرسال كود مادة
    if ($user->role === 'student' && $request->has('access_code')) {
        $teacherSubjectGrade = TeacherSubjectGrade::where('access_code', $request->access_code)->first();
        
        if ($teacherSubjectGrade) {
            // البحث عن اشتراك موجود
            $existingSubscription = StudentSubscription::where([
                'student_id' => $user->id,
                'teacher_subject_grade_id' => $teacherSubjectGrade->id,
            ])->first();

            if (!$existingSubscription) {
                // إنشاء اشتراك جديد
                StudentSubscription::create([
                    'student_id' => $user->id,
                    'teacher_subject_grade_id' => $teacherSubjectGrade->id,
                    'status' => 'active',
                    'subscribed_at' => now(),
                ]);
            } else {
                // لو الاشتراك موجود لكن cancelled أو expired، نفعله
                if (in_array($existingSubscription->status, ['cancelled', 'expired'])) {
                    $existingSubscription->update([
                        'status' => 'active',
                        'subscribed_at' => now(),
                    ]);
                }
            }
        }
    }

    return response()->json([
        'success' => true,
        'message' => 'تم تحديث المستخدم بنجاح',
        'data' => [
            'user' => $user,
            // ✅ إرجاع معلومات الاشتراك لو طالب
            'subscription' => $user->role === 'student' ? $this->getStudentSubscriptions($user->id) : null,
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}

/**
 * جلب اشتراكات الطالب
 */
// private function getStudentSubscriptions($studentId)
// {
//     $subscriptions = StudentSubscription::where('student_id', $studentId)
//         ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade', 'teacherSubjectGrade.teacher'])
//         ->get();

//     return $subscriptions->map(function($subscription) {
//         return [
//             'id' => $subscription->id,
//             'subject' => $subscription->teacherSubjectGrade->subject->name,
//             'grade' => $subscription->teacherSubjectGrade->grade->name,
//             'teacher' => $subscription->teacherSubjectGrade->teacher->name,
//             'access_code' => $subscription->teacherSubjectGrade->access_code,
//             'status' => $subscription->status,
//             'subscribed_at' => $subscription->subscribed_at,
//             'expires_at' => $subscription->expires_at,
//         ];
//     });
// }

    /**
     * حذف مستخدم
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        // منع حذف الأدمن نفسه
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك حذف حسابك'
            ], 403);
        }

        // حذف الصورة لو موجودة
        if ($user->image) {
            Storage::disk('public')->delete($user->image);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المستخدم بنجاح'
        ]);
    }

    /**
     * تغيير حالة المستخدم (تفعيل/تعطيل)
     */
    public function toggleStatus($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        // منع تعطيل الأدمن نفسه
        if ($user->id === auth()->id() && $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك تعطيل حسابك'
            ], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'تم تفعيل المستخدم' : 'تم تعطيل المستخدم',
            'data' => $user
        ]);
    }


}
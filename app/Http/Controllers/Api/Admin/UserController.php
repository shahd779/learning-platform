<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog;
use App\LogsActivity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;
use App\Exports\UsersExport;
use App\Exports\BlockedUsersExport;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    use LogsActivity;

    /**
     * إحصائيات المستخدمين
     */
public function stats()
{
    $totalUsers = User::count();
    $admins = User::where('role', 'admin')->count();
    $teachers = User::where('role', 'teacher')->count();
    $students = User::where('role', 'student')->count();

    // ✅ الحسابات الموقوفة (المدرسين والأدمن: is_active = false)
    $blockedAdmins = User::where('role', 'admin')->where('is_active', false)->count();
    $blockedTeachers = User::where('role', 'teacher')->where('is_active', false)->count();

    // ✅ الطلاب الموقوفين: is_active = false OR عنده اشتراك محظور
    $blockedStudents = User::where('role', 'student')
        ->where(function ($q) {
            $q->where('is_active', false)
              ->orWhereHas('studentSubscriptions', function ($q2) {
                  $q2->where('is_banned', true);
              });
        })
        ->distinct('id')
        ->count();

    // ✅ إجمالي الحسابات الموقوفة
    $blockedUsers = $blockedAdmins + $blockedTeachers + $blockedStudents;

    // المدرسين النشطين (اللي عندهم مواد)
    $activeTeachers = TeacherSubjectGrade::distinct('teacher_id')->count();

    // الطلاب النشطين (اللي عندهم اشتراكات نشطة وغير محظورة)
    $activeStudents = StudentSubscription::where('status', 'active')
        ->where('is_banned', false)
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



    public function exportUsers(Request $request)
{
    try {
        $fileName = 'المستخدمين_' . date('Y_m_d') . '.xlsx';
        $filePath = 'exports/' . $fileName;
        
        // ✅ تخزين الملف
        Excel::store(new UsersExport($request), $filePath, 'public');
        
        // ✅ جلب الرابط
        $fileUrl = url('/storage/' . $filePath);
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
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء التصدير: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * تصدير المستخدمين المتوقفين (يرجع رابط تحميل)
 */
public function exportBlockedUsers(Request $request)
{
    try {
        $fileName = 'المستخدمين_المتوقفين_' . date('Y_m_d') . '.xlsx';
        $filePath = 'exports/' . $fileName;
        
        // ✅ تخزين الملف
        Excel::store(new BlockedUsersExport($request), $filePath, 'public');
        
        // ✅ جلب الرابط
        $fileUrl = url('/storage/' . $filePath);
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
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ أثناء التصدير: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * عرض كل المستخدمين مع فلترة وبحث
     */
    public function index(Request $request)
    {
        $query = User::query();

        
    // ✅ الحسابات الموقوفة (المدرسين والأدمن: is_active = false)
    $blockedAdmins = User::where('role', 'admin')->where('is_active', false)->count();
    $blockedTeachers = User::where('role', 'teacher')->where('is_active', false)->count();

    // ✅ الطلاب الموقوفين: is_active = false OR عنده اشتراك محظور
    $blockedStudents = User::where('role', 'student')
        ->where(function ($q) {
            $q->where('is_active', false)
              ->orWhereHas('studentSubscriptions', function ($q2) {
                  $q2->where('is_banned', true);
              });
        })
        ->distinct('id')
        ->count();

    // ✅ إجمالي الحسابات الموقوفة
    $blockedUsers = $blockedAdmins + $blockedTeachers + $blockedStudents;


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
                        'package_id' => $subscription->package_id,
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
                        'package_id' => $lastActive->package_id,
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
            'blocked_users' => $blockedUsers,
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
     * جلب المستخدمين المتوقفين / المحظورين
     */
    public function blockedUsers(Request $request)
    {
        $query = User::query();

        // ✅ شرط الحظر الشامل
        $query->where(function ($q) {
            // 1. المدرسين والأدمن: is_active = false
            $q->where(function ($q2) {
                $q2->whereIn('role', ['admin', 'teacher'])
                   ->where('is_active', false);
            })
            // 2. الطلاب: is_active = false OR عنده اشتراك محظور
            ->orWhere(function ($q2) {
                $q2->where('role', 'student')
                   ->where(function ($q3) {
                       $q3->where('is_active', false)
                          ->orWhereHas('studentSubscriptions', function ($q4) {
                              $q4->where('is_banned', true);
                          });
                   });
            });
        });

        // فلترة حسب الدور
        if ($request->has('role') && $request->role && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // فلترة حسب حالة اشتراك الطالب
        if ($request->has('subscription_status') && $request->subscription_status !== 'all') {
            $subscriptionStatus = $request->subscription_status;
            $query->whereHas('studentSubscriptions', function ($q) use ($subscriptionStatus) {
                $q->where('status', $subscriptionStatus);
            });
        }

        // فلترة حسب تاريخ الاشتراك
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

        // فلترة حسب تاريخ انتهاء الاشتراك
        if ($request->has('expires_from') && $request->expires_from) {
            $query->whereHas('studentSubscriptions', function ($q) use ($request) {
                $q->whereNotNull('expires_at')
                  ->whereDate('expires_at', '>=', $request->expires_from);
            });
        }

        if ($request->has('expires_to') && $request->expires_to) {
            $query->whereHas('studentSubscriptions', function ($q) use ($request) {
                $q->whereNotNull('expires_at')
                  ->whereDate('expires_at', '<=', $request->expires_to);
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

        // ✅ تحويل البيانات
        $users->getCollection()->transform(function ($user) {
            
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'image' => $user->image,
                'image_url' => $user->image_url,
                'created_at' => $user->created_at->format('Y-m-d'),
            ];

            // ===== المدرسين =====
            if ($user->role === 'teacher') {
                $subjects = TeacherSubjectGrade::where('teacher_id', $user->id)
                    ->with(['subject', 'grade'])
                    ->get();
                
                $userData['subjects'] = $subjects->map(function($item) {
                    return [
                        'subject' => $item->subject->name,
                        'grade' => $item->grade->name,
                        'is_active' => $item->is_active,
                    ];
                });
                $userData['subjects_count'] = $subjects->count();
                $userData['ban_type'] = 'full';
            }

            // ===== الطلاب =====
            if ($user->role === 'student') {
                $subscriptions = StudentSubscription::where('student_id', $user->id)
                    ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade', 'teacherSubjectGrade.teacher'])
                    ->get();

                $bannedSubscriptions = $subscriptions->filter(function($sub) {
                    return $sub->is_banned == true;
                });

                $activeSubscriptions = $subscriptions->filter(function($sub) {
                    return $sub->is_banned == false && $sub->status === 'active';
                });

                $expiredSubscriptions = $subscriptions->filter(function($sub) {
                    return $sub->status === 'expired';
                });

                $userData['banned_subjects'] = $bannedSubscriptions->map(function($subscription) {
                    return [
                        'subscription_id' => $subscription->id,
                        'subject' => $subscription->teacherSubjectGrade->subject->name,
                        'grade' => $subscription->teacherSubjectGrade->grade->name,
                        'teacher' => $subscription->teacherSubjectGrade->teacher->name,
                        'access_code' => $subscription->teacherSubjectGrade->access_code,
                        'status' => $subscription->status,
                        'package_id' => $subscription->package_id,
                        'banned_by' => $subscription->banned_by ? User::find($subscription->banned_by)->name : null,
                        'ban_reason' => $subscription->ban_reason,
                        'subscribed_at' => $subscription->subscribed_at ? $subscription->subscribed_at->format('Y-m-d') : null,
                        'expires_at' => $subscription->expires_at ? $subscription->expires_at->format('Y-m-d') : null,
                    ];
                });

                $userData['active_subjects'] = $activeSubscriptions->map(function($subscription) {
                    return [
                        'subscription_id' => $subscription->id,
                        'subject' => $subscription->teacherSubjectGrade->subject->name,
                        'grade' => $subscription->teacherSubjectGrade->grade->name,
                        'teacher' => $subscription->teacherSubjectGrade->teacher->name,
                        'access_code' => $subscription->teacherSubjectGrade->access_code,
                        'status' => $subscription->status,
                        'subscribed_at' => $subscription->subscribed_at ? $subscription->subscribed_at->format('Y-m-d') : null,
                        'expires_at' => $subscription->expires_at ? $subscription->expires_at->format('Y-m-d') : null,
                    ];
                });

                $userData['expired_subjects'] = $expiredSubscriptions->map(function($subscription) {
                    return [
                        'subscription_id' => $subscription->id,
                        'subject' => $subscription->teacherSubjectGrade->subject->name,
                        'grade' => $subscription->teacherSubjectGrade->grade->name,
                        'teacher' => $subscription->teacherSubjectGrade->teacher->name,
                        'status' => $subscription->status,
                        'subscribed_at' => $subscription->subscribed_at ? $subscription->subscribed_at->format('Y-m-d') : null,
                        'expires_at' => $subscription->expires_at ? $subscription->expires_at->format('Y-m-d') : null,
                    ];
                });

                $userData['total_subscriptions'] = $subscriptions->count();
                $userData['banned_count'] = $bannedSubscriptions->count();
                $userData['active_count'] = $activeSubscriptions->count();
                $userData['expired_count'] = $expiredSubscriptions->count();

                if ($user->is_active == false) {
                    $userData['ban_type'] = 'full_account';
                } elseif ($bannedSubscriptions->count() > 0) {
                    $userData['ban_type'] = 'partial_subjects';
                } else {
                    $userData['ban_type'] = 'none';
                }
            }

            if ($user->role === 'admin') {
                $userData['ban_type'] = 'full';
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
            'package_id' => 'nullable|exists:packages,id',
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

        // ✅ تسجيل النشاط - إضافة مستخدم
        $this->logActivity(
            'إضافة مستخدم جديد',
            "تم إضافة {$request->role} جديد باسم {$request->name} بواسطة " . auth()->user()->name,
            'create'
        );

        // ✅ لو طالب ومدخل كود
        if ($request->role === 'student' && $request->has('access_code')) {
            $teacherSubjectGrade = TeacherSubjectGrade::where('access_code', $request->access_code)->first();
            
            if ($teacherSubjectGrade) {
                $expiresAt = null;
                if ($request->has('package_id') && $request->package_id) {
                    $package = \App\Models\Package::find($request->package_id);
                    if ($package && $package->duration_days) {
                        $expiresAt = now()->addDays($package->duration_days);
                    }
                }

                $subscription = StudentSubscription::create([
                    'student_id' => $user->id,
                    'teacher_subject_grade_id' => $teacherSubjectGrade->id,
                    'package_id' => $request->package_id ?? null,
                    'status' => 'active',
                    'subscribed_at' => now(),
                    'expires_at' => $expiresAt,
                    'is_free' => $request->package_id ? false : true,
                ]);

                if ($request->has('package_id') && $request->package_id) {
                    $package = \App\Models\Package::find($request->package_id);
                    if ($package && $package->max_subscriptions) {
                        // package->decrement('remaining_subscriptions');
                    }
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
                'subscription' => isset($subscription) ? [
                    'id' => $subscription->id,
                    'subject' => $teacherSubjectGrade->subject->name,
                    'grade' => $teacherSubjectGrade->grade->name,
                    'teacher' => $teacherSubjectGrade->teacher->name,
                    'access_code' => $teacherSubjectGrade->access_code,
                    'status' => $subscription->status,
                    'expires_at' => $subscription->expires_at,
                ] : null,
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
            'access_code' => 'sometimes|required_if:role,student|exists:teacher_subject_grade,access_code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldName = $user->name;
        $data = $request->only(['name', 'phone', 'role', 'is_active']);
        
        if ($request->has('password') && $request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // ✅ تسجيل النشاط - تحديث مستخدم
        $this->logActivity(
            'تعديل بيانات مستخدم',
            "تم تعديل بيانات المستخدم {$oldName} بواسطة " . auth()->user()->name,
            'update'
        );

        // ✅ لو المستخدم طالب وتم إرسال كود مادة
        if ($user->role === 'student' && $request->has('access_code')) {
            $teacherSubjectGrade = TeacherSubjectGrade::where('access_code', $request->access_code)->first();
            
            if ($teacherSubjectGrade) {
                $existingSubscription = StudentSubscription::where([
                    'student_id' => $user->id,
                    'teacher_subject_grade_id' => $teacherSubjectGrade->id,
                ])->first();

                if (!$existingSubscription) {
                    StudentSubscription::create([
                        'student_id' => $user->id,
                        'teacher_subject_grade_id' => $teacherSubjectGrade->id,
                        'status' => 'active',
                        'subscribed_at' => now(),
                    ]);
                } else {
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
                'subscription' => $user->role === 'student' ? $this->getStudentSubscriptions($user->id) : null,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب اشتراكات الطالب
     */
    private function getStudentSubscriptions($studentId)
    {
        $subscriptions = StudentSubscription::where('student_id', $studentId)
            ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade', 'teacherSubjectGrade.teacher'])
            ->get();

        return $subscriptions->map(function($subscription) {
            return [
                'id' => $subscription->id,
                'subject' => $subscription->teacherSubjectGrade->subject->name,
                'grade' => $subscription->teacherSubjectGrade->grade->name,
                'teacher' => $subscription->teacherSubjectGrade->teacher->name,
                'access_code' => $subscription->teacherSubjectGrade->access_code,
                'status' => $subscription->status,
                'subscribed_at' => $subscription->subscribed_at,
                'expires_at' => $subscription->expires_at,
            ];
        });
    }

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

        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك حذف حسابك'
            ], 403);
        }

        $userName = $user->name;

        if ($user->image) {
            Storage::disk('public')->delete($user->image);
        }

        $user->delete();

        // ✅ تسجيل النشاط - حذف مستخدم
        $this->logActivity(
            'حذف مستخدم',
            "تم حذف المستخدم {$userName} بواسطة " . auth()->user()->name,
            'delete'
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المستخدم بنجاح'
        ]);
    }

    /**
     * Ban/Unban طالب من مادة معينة
     */
    public function toggleStudentSubjectBan($studentId, $subscriptionId)
    {
        $subscription = StudentSubscription::where('student_id', $studentId)
            ->where('id', $subscriptionId)
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'الاشتراك غير موجود'
            ], 404);
        }

        $currentUser = auth()->user();

        if ($currentUser->role === 'teacher') {
            $teacherSubject = TeacherSubjectGrade::where('teacher_id', $currentUser->id)
                ->where('id', $subscription->teacher_subject_grade_id)
                ->exists();
            
            if (!$teacherSubject) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكنك التعامل مع هذا الطالب'
                ], 403);
            }
        }

        if (!in_array($currentUser->role, ['admin', 'teacher'])) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بهذا الإجراء'
            ], 403);
        }

        $subscription->is_banned = !$subscription->is_banned;
        $subscription->banned_at = $subscription->is_banned ? now() : null;
        $subscription->banned_by = $subscription->is_banned ? $currentUser->id : null;
        $subscription->ban_reason = $subscription->is_banned ? 'تم الحظر من قبل ' . $currentUser->name : null;
        $subscription->save();

        // ✅ تسجيل النشاط
        $action = $subscription->is_banned ? 'حظر' : 'فك الحظر عن';
        $this->logActivity(
            $subscription->is_banned ? 'حظر طالب من مادة' : 'فك الحظر عن طالب من مادة',
            "تم {$action} الطالب {$subscription->student->name} من مادة {$subscription->teacherSubjectGrade->subject->name} بواسطة " . $currentUser->name,
            $subscription->is_banned ? 'ban' : 'unban'
        );

        return response()->json([
            'success' => true,
            'message' => $subscription->is_banned ? 'تم حظر الطالب من هذه المادة' : 'تم فك الحظر عن الطالب من هذه المادة',
            'data' => [
                'student' => $subscription->student->name,
                'subject' => $subscription->teacherSubjectGrade->subject->name,
                'is_banned' => $subscription->is_banned,
                'action_by' => $currentUser->name,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ban/Unban طالب من كل المواد
     */
    public function toggleStudentAllBan($studentId)
    {
        $student = User::where('role', 'student')->find($studentId);

        if (!$student) {
            return response()->json([
                'success' => false,
                'message' => 'الطالب غير موجود'
            ], 404);
        }

        $currentUser = auth()->user();

        if ($currentUser->role === 'teacher') {
            $hasStudent = StudentSubscription::where('student_id', $studentId)
                ->whereHas('teacherSubjectGrade', function($q) use ($currentUser) {
                    $q->where('teacher_id', $currentUser->id);
                })
                ->exists();

            if (!$hasStudent) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكنك التعامل مع هذا الطالب'
                ], 403);
            }
        }

        if (!in_array($currentUser->role, ['admin', 'teacher'])) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بهذا الإجراء'
            ], 403);
        }

        $firstSubscription = StudentSubscription::where('student_id', $studentId)->first();

        if (!$firstSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'الطالب ليس لديه اشتراكات'
            ], 422);
        }

        $newBanStatus = !$firstSubscription->is_banned;

        StudentSubscription::where('student_id', $studentId)
            ->update([
                'is_banned' => $newBanStatus,
                'banned_at' => $newBanStatus ? now() : null,
                'banned_by' => $newBanStatus ? $currentUser->id : null,
                'ban_reason' => $newBanStatus ? 'تم حظر الطالب من كل المواد بواسطة ' . $currentUser->name : null,
            ]);

        // ✅ تسجيل النشاط
        $action = $newBanStatus ? 'حظر' : 'فك الحظر عن';
        $this->logActivity(
            $newBanStatus ? 'حظر طالب من كل المواد' : 'فك الحظر عن طالب من كل المواد',
            "تم {$action} الطالب {$student->name} من كل المواد بواسطة " . $currentUser->name,
            $newBanStatus ? 'ban' : 'unban'
        );

        return response()->json([
            'success' => true,
            'message' => $newBanStatus ? 'تم حظر الطالب من كل المواد' : 'تم فك الحظر عن الطالب من كل المواد',
            'data' => [
                'student' => $student->name,
                'is_banned' => $newBanStatus,
                'action_by' => $currentUser->name,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ban/Unban مستخدم (مدرس أو أدمن)
     * لو نشط → يعطله
     * لو غير نشط → يفعل
     */
    public function toggleUserBan($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        // ✅ الطلاب ليهم نظام تاني
        if ($user->role === 'student') {
            return response()->json([
                'success' => false,
                'message' => 'استخدم نظام حظر الطلاب المخصص'
            ], 422);
        }

        $currentUser = auth()->user();

        // ✅ منع تعطيل نفسه
        if ($user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك تغيير حالة حسابك'
            ], 403);
        }

        // ✅ منع تعطيل الأدمن الوحيد (لو كان نشط)
        if ($user->role === 'admin' && $user->is_active) {
            $adminCount = User::where('role', 'admin')->where('is_active', true)->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يمكن تعطيل الأدمن الوحيد في المنصة'
                ], 403);
            }
        }

        // ✅ فقط الأدمن يقدر يغير حالة مدرس أو أدمن
        if ($currentUser->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بهذا الإجراء'
            ], 403);
        }

        // ✅ تبديل الحالة
        $user->is_active = !$user->is_active;
        $user->save();

        // ✅ تسجيل النشاط
        $action = $user->is_active ? 'تفعيل' : 'تعطيل';
        $this->logActivity(
            $user->is_active ? 'تفعيل مستخدم' : 'تعطيل مستخدم',
            "تم {$action} المستخدم {$user->name} بواسطة " . $currentUser->name,
            $user->is_active ? 'unban' : 'ban'
        );

        // ✅ لو المدرس، نغير حالة المواد بتاعته
        if ($user->role === 'teacher') {
            TeacherSubjectGrade::where('teacher_id', $user->id)
                ->update(['is_active' => $user->is_active]);
        }

        return response()->json([
            'success' => true,
            'message' => $user->is_active ? 'تم تفعيل المستخدم بنجاح' : 'تم تعطيل المستخدم بنجاح',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'action_by' => $currentUser->name,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
 * جلب كل المواد المشترك فيها طالب معين (للدروب داون)
 */
/**
 * جلب كل المواد المشترك فيها طالب معين (للدروب داون)
 */
public function getStudentSubjects($studentId)
{
    $student = User::where('role', 'student')->find($studentId);

    if (!$student) {
        return response()->json([
            'success' => false,
            'message' => 'الطالب غير موجود'
        ], 404);
    }

    $subscriptions = StudentSubscription::where('student_id', $studentId)
        ->where('status', 'active')
        ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade', 'teacherSubjectGrade.teacher'])
        ->get();

    // ✅ فلترة الاشتراكات اللي فيها بيانات كاملة
    $validSubscriptions = $subscriptions->filter(function($subscription) {
        return $subscription->teacherSubjectGrade !== null 
            && $subscription->teacherSubjectGrade->subject !== null
            && $subscription->teacherSubjectGrade->grade !== null;
    });

    return response()->json([
        'success' => true,
        'data' => $validSubscriptions->map(function($subscription) {
            return [
                'subscription_id' => $subscription->id,
                'subject' => $subscription->teacherSubjectGrade->subject->name ?? 'محذوف',
                
            ];
        })
    ], 200, [], JSON_UNESCAPED_UNICODE);
}
}
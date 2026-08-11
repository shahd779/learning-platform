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

        // فلترة حسب الدور
        if ($request->has('role') && $request->role && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // فلترة حسب الحالة
        if ($request->has('status') && $request->status !== 'all') {
            $isActive = $request->status === 'active';
            $query->where('is_active', $isActive);
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

        // إضافة إحصائيات لكل مستخدم
        $users->getCollection()->transform(function ($user) {
            if ($user->role === 'teacher') {
                $user->subjects_count = TeacherSubjectGrade::where('teacher_id', $user->id)->count();
                $user->students_count = StudentSubscription::whereHas('teacherSubjectGrade', function ($q) use ($user) {
                    $q->where('teacher_id', $user->id);
                })->where('status', 'active')->distinct('student_id')->count();
            }
            
            if ($user->role === 'student') {
                $lastSubscription = StudentSubscription::where('student_id', $user->id)
                    ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
                    ->where('status', 'active')
                    ->latest()
                    ->first();
                $user->last_subscription = $lastSubscription;
            }
            
            return $user;
        });

        return response()->json([
            'success' => true,
            'data' => $users,
            'filters' => [
                'role' => $request->role,
                'status' => $request->status,
                'search' => $request->search,
            ]
        ]);
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
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // رفع الصورة لو موجودة
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('users', 'public');
        }

        // إنشاء المستخدم
        $user = User::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'image' => $imagePath,
            'is_active' => $request->is_active ?? true,
        ]);

        $message = $request->role === 'teacher' 
            ? 'تم إضافة المدرس بنجاح. يمكنه تسجيل الدخول وطلب إضافة مادة.'
            : 'تم إضافة الطالب بنجاح. يمكنه تسجيل الدخول باستخدام رقم الهاتف وكلمة المرور وكود المادة.';

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
                ]
            ]
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المستخدم بنجاح',
            'data' => $user
        ]);
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

    /**
     * إعادة تعيين كلمة المرور
     */
    public function resetPassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:6',
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'المستخدم غير موجود'
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إعادة تعيين كلمة المرور بنجاح',
            'data' => [
                'phone' => $user->phone,
                'new_password' => $request->password,
            ]
        ]);
    }
}
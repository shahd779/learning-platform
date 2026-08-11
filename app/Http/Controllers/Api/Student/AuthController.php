<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * تسجيل دخول الطالب
     * الطالب يدخل: رقم الهاتف + كلمة المرور + كود المادة
     */
    public function login(Request $request)
    {
        // 1. التحقق من البيانات
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
            'password' => 'required|string|min:6',
            'access_code' => 'required|string|exists:teacher_subject_grade,access_code',
        ]);

        // 2. التحقق من كلمة المرور
        $user = User::where('phone', $request->phone)->first();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الهاتف أو كلمة المرور غير صحيحة'
            ], 401);
        }

        // 3. التأكد من أن المستخدم طالب
        if ($user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'هذا الحساب ليس طالباً'
            ], 403);
        }

        // 4. التأكد من أن الحساب نشط
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'الحساب غير نشط، برجاء التواصل مع الدعم'
            ], 403);
        }

        // 5. البحث عن كود المادة
        $teacherSubjectGrade = TeacherSubjectGrade::with(['subject', 'grade', 'teacher'])
            ->where('access_code', $request->access_code)
            ->first();

        if (!$teacherSubjectGrade || !$teacherSubjectGrade->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'كود المادة غير صحيح أو غير نشط'
            ], 404);
        }

        // 6. التحقق من اشتراك الطالب في المادة
        $subscription = StudentSubscription::where([
            'student_id' => $user->id,
            'teacher_subject_grade_id' => $teacherSubjectGrade->id,
        ])->first();

        // لو مش اشتراك، نعمل اشتراك جديد
        if (!$subscription) {
            $subscription = StudentSubscription::create([
                'student_id' => $user->id,
                'teacher_subject_grade_id' => $teacherSubjectGrade->id,
                'status' => 'active',
                'subscribed_at' => now(),
            ]);
        }

        // 7. التحقق من صلاحية الاشتراك (لو في تاريخ انتهاء)
        if ($subscription->status === 'expired' || 
            ($subscription->expires_at && $subscription->expires_at < now())) {
            return response()->json([
                'success' => false,
                'message' => 'انتهى اشتراكك في هذه المادة، برجاء التجديد'
            ], 403);
        }

        // 8. تحديث حالة الاشتراك لو مش active
        if ($subscription->status !== 'active') {
            $subscription->update(['status' => 'active']);
        }

        // 9. إنشاء Token للمستخدم
        $token = $user->createToken('student-token', ['*'], now()->addDays(30))->plainTextToken;

        // 10. الرد
        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'image' => $user->image,
                ],
                'subject' => [
                    'id' => $teacherSubjectGrade->subject->id,
                    'name' => $teacherSubjectGrade->subject->name,
                    'code' => $teacherSubjectGrade->subject->code,
                    'grade' => $teacherSubjectGrade->grade->name,
                    'teacher' => $teacherSubjectGrade->teacher->name,
                ],
                'subscription' => [
                    'id' => $subscription->id,
                    'status' => $subscription->status,
                    'subscribed_at' => $subscription->subscribed_at,
                    'expires_at' => $subscription->expires_at,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    /**
     * تسجيل خروج الطالب
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }

    /**
     * عرض مواد الطالب
     */
    public function subjects(Request $request)
    {
        $user = $request->user();
        
        $subscriptions = StudentSubscription::where('student_id', $user->id)
            ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade', 'teacherSubjectGrade.teacher'])
            ->where('status', 'active')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subject;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'subject_code' => 'required|string|exists:subjects,code',
            'phone' => 'required|string|exists:users,phone',
            'password' => 'required|string|min:6',
        ]);

        // البحث عن المادة
        $subject = Subject::where('code', $request->subject_code)->first();

        // التحقق من بيانات الطالب
        $user = User::where('phone', $request->phone)->first();

        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور غير صحيحة'
            ], 401);
        }

        // التأكد من أن المستخدم طالب
        if ($user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول كطالب'
            ], 403);
        }

        // التحقق من وجود اشتراك نشط لهذه المادة
        $subscription = Subscription::where('student_id', $user->id)
            ->where('subject_id', $subject->id)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'ليس لديك اشتراك نشط في هذه المادة'
            ], 403);
        }

        // إنشاء Token مع إضافة معلومات المادة
        $token = $user->createToken('student-token', ['*'], now()->addDays(30))->plainTextToken;

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
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'grade' => $subject->grade->name ?? null,
                ],
                'subscription' => [
                    'id' => $subscription->id,
                    'package' => $subscription->package->name ?? null,
                    'expires_at' => $subscription->end_date,
                ],
                'token' => $token,
            ]
        ]);
    }
}
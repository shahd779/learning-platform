<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. التحقق من البيانات
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
            'password' => 'required|string|min:6',
        ]);

        // 2. محاولة تسجيل الدخول
        $credentials = $request->only('phone', 'password');
        
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الهاتف أو كلمة المرور غير صحيحة'
            ], 401);
        }

        // 3. جلب المستخدم
        $user = User::where('phone', $request->phone)->first();

        // 4. التأكد من أن المستخدم أدمن
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول كأدمن'
            ], 403);
        }

        // 5. التأكد من أن الحساب نشط
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'الحساب غير نشط، برجاء التواصل مع الدعم'
            ], 403);
        }

        // 6. إنشاء Token
        $token = $user->createToken('admin-token')->plainTextToken;

        // 7. الرد
        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'image' => $user->image,
                    'role' => $user->role,
                ],
                'token' => $token,
                'token_type' => 'Bearer',
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج بنجاح'
        ]);
    }
}
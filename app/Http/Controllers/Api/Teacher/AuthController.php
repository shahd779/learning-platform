<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|exists:users,phone',
            'password' => 'required|string|min:6',
        ]);

        if (!Auth::attempt($request->only('phone', 'password'))) {
            return response()->json([
                'success' => false,
                'message' => 'رقم الهاتف أو كلمة المرور غير صحيحة'
            ], 401);
        }

        $user = User::where('phone', $request->phone)->first();

        // التأكد من أن المستخدم مدرس
        if ($user->role !== 'teacher') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالدخول كمدرس'
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'الحساب غير نشط'
            ], 403);
        }

        $token = $user->createToken('teacher-token')->plainTextToken;

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
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الخروج'
        ]);
    }
}
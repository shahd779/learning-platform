<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * جلب الملف الشخصي للمستخدم الحالي
     */
    public function show()
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'image' => $user->image ? asset('storage/' . $user->image) : null,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    /**
     * تحديث الملف الشخصي
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|unique:users,phone,' . $user->id,
            'image' => 'nullable|image|max:2048', // 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'phone']);

        // معالجة الصورة
        if ($request->hasFile('image')) {
            // حذف الصورة القديمة
            if ($user->image) {
                Storage::disk('public')->delete($user->image);
            }
            
            $data['image'] = $request->file('image')->store('users', 'public');
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'image' => $user->image ? asset('storage/' . $user->image) : null,
            ]
        ]);
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string|min:6',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // التحقق من كلمة المرور الحالية
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة'
            ], 422);
        }

        // تحديث كلمة المرور
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    }

    /**
     * حذف الصورة الشخصية
     */
    public function deleteImage()
    {
        $user = Auth::user();

        if (!$user->image) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد صورة لحذفها'
            ], 422);
        }

        // حذف الصورة من التخزين
        Storage::disk('public')->delete($user->image);
        
        // تحديث قاعدة البيانات
        $user->update(['image' => null]);

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصورة بنجاح'
        ]);
    }

}
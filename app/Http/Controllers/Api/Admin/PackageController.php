<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{

    public function index(Request $request)
    {
        $query = Package::query();

        // فلترة حسب الحالة
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->is_active);
        }

        // بحث بالاسم
        if ($request->has('search') && $request->search) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $packages = $query->orderBy('price', 'asc')->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $packages
        ]);
    }
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:packages,name',
            'price' => 'required|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
            'description' => 'nullable|string|max:1000',
            'duration_days' => 'required|integer|min:1|max:365',
            'features' => 'nullable|array',
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
            'description' => $request->description,
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
            'description' => 'nullable|string|max:1000',
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

        $data = $request->only(['name', 'price', 'description', 'duration_days', 'is_active']);

        // معالجة المميزات
        if ($request->has('features')) {
            $data['features'] = $request->features;
        }

        $package->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الباقة بنجاح',
            'data' => $package
        ]);
    }

    public function toggleStatus($id)
    {
        $package = Package::find($id);

        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'الباقة غير موجودة'
            ], 404);
        }

        $package->is_active = !$package->is_active;
        $package->save();

        return response()->json([
            'success' => true,
            'message' => $package->is_active ? 'تم تفعيل الباقة' : 'تم تعطيل الباقة',
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

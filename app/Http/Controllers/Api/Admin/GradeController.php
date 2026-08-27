<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Grade;
use Illuminate\Support\Facades\Validator;
use App\LogsActivity; // ✅ أضفنا

class GradeController extends Controller
{
    use LogsActivity; // ✅ استخدمنا الـ Trait

    /**
     * عرض كل الصفوف
     */
    public function index(Request $request)
    {
        $query = Grade::query();

        // بحث بالاسم أو الكود
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('code', 'LIKE', '%' . $search . '%');
            });
        }

        // ✅ تحديد عدد النتائج في الصفحة
        $perPage = $request->has('per_page') ? (int)$request->per_page : 15;
        
        // منع القيم الغريبة
        if ($perPage < 1) $perPage = 1;
        if ($perPage > 100) $perPage = 100;

        $grades = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $grades
        ]);
    }

    /**
     * إضافة صف جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:grades,code|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $grade = Grade::create($request->all());

        // ✅ تسجيل النشاط - إضافة صف
        $this->logActivity(
            'إضافة صف جديد',
            "تم إضافة صف جديد باسم {$request->name} وكود {$request->code} بواسطة " . auth()->user()->name,
            'create'
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الصف بنجاح',
            'data' => $grade
        ]);
    }

    /**
     * تحديث صف
     */
    public function update(Request $request, $id)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'الصف غير موجود'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:grades,code,' . $id . '|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'code']);
        
        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد بيانات للتحديث'
            ], 422);
        }

        $oldName = $grade->name;
        $grade->update($data);

        // ✅ تسجيل النشاط - تحديث صف
        $this->logActivity(
            'تعديل صف',
            "تم تعديل بيانات الصف {$oldName} بواسطة " . auth()->user()->name,
            'update'
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الصف بنجاح',
            'data' => $grade
        ]);
    }

    /**
     * حذف صف
     */
    public function destroy($id)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'الصف غير موجود'
            ], 404);
        }

        $gradeName = $grade->name;
        $grade->delete();

        // ✅ تسجيل النشاط - حذف صف
        $this->logActivity(
            'حذف صف',
            "تم حذف الصف {$gradeName} بواسطة " . auth()->user()->name,
            'delete'
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصف بنجاح'
        ]);
    }
}
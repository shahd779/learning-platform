<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Grade;
use Illuminate\Support\Facades\Validator;

class GradeController extends Controller
{
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

        $grades = $query->latest()->paginate($request->per_page ?? 15);

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

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الصف بنجاح',
            'data' => $grade
        ]);
    }

    /**
     * عرض صف معين
     */
    public function show($id)
    {
        $grade = Grade::find($id);

        if (!$grade) {
            return response()->json([
                'success' => false,
                'message' => 'الصف غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
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

    // ✅ التعديل هنا: خدي البيانات اللي جاية بس
    $data = $request->only(['name', 'code']);
    
    // ✅ لو مفيش بيانات ابعتي Error
    if (empty($data)) {
        return response()->json([
            'success' => false,
            'message' => 'لا توجد بيانات للتحديث'
        ], 422);
    }

    $grade->update($data);

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
        $grade->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصف بنجاح'
        ]);
    }

    /**
     * جلب كل المواد التابعة لصف معين
     */
   
}
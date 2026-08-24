<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subject;
use App\Models\Grade;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class SubjectController extends Controller
{


/**
 * إحصائيات المواد والصفوف (شاملة)
 */
public function overview()
{
    $totalSubjects = Subject::count();
    
    $totalGrades = Grade::count();

    return response()->json([
        'success' => true,
        'data' => [
            'subjects' => [
                'total' => $totalSubjects,
            ],
            'grades' => [
                'total' => $totalGrades,
            ],
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}
    /**
     * عرض كل المواد
     */
public function index(Request $request)
{
    $query = Subject::query();

    // فلترة حسب الحالة
    if ($request->has('is_active') && $request->is_active !== '') {
        $query->where('is_active', $request->is_active);
    }

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

    $subjects = $query->latest()->paginate($perPage);

    return response()->json([
        'success' => true,
        'data' => $subjects
    ], 200, [], JSON_UNESCAPED_UNICODE);
}

    /**
     * إضافة مادة جديدة
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:subjects,code|max:50',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }


        $subject = Subject::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المادة بنجاح',
            'data' => $subject
        ]);
    }



    /**
     * تحديث مادة
     */
    public function update(Request $request, $id)
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|unique:subjects,code,' . $id . '|max:50',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['name', 'code', 'description', 'is_active']);

        // رفع الصورة الجديدة
        if ($request->hasFile('image')) {
            // حذف الصورة القديمة
            if ($subject->image) {
                Storage::disk('public')->delete($subject->image);
            }
            $data['image'] = $request->file('image')->store('subjects', 'public');
        }

        $subject->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث المادة بنجاح',
            'data' => $subject->fresh()
        ]);
    }

    /**
     * حذف مادة
     */
    public function destroy($id)
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة'
            ], 404);
        }

        // حذف الصورة
        if ($subject->image) {
            Storage::disk('public')->delete($subject->image);
        }

        $subject->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف المادة بنجاح'
        ]);
    }

    /**
     * تغيير حالة المادة (تفعيل/تعطيل)
     */
    public function toggleStatus($id)
    {
        $subject = Subject::find($id);

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة'
            ], 404);
        }

        $subject->is_active = !$subject->is_active;
        $subject->save();

        return response()->json([
            'success' => true,
            'message' => $subject->is_active ? 'تم تفعيل المادة' : 'تم تعطيل المادة',
            'data' => $subject
        ]);
    }

    /**
     * جلب كل المواد المتاحة (للدروب داون)
     */
    public function options()
    {
        $subjects = Subject::where('is_active', true)
            ->get(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data' => $subjects
        ]);
    }

    /**
     * جلب المواد المتاحة لمدرس معين
     */
    public function availableForTeacher(Request $request)
    {
        $teacherId = $request->teacher_id;
        
        $subjects = Subject::where('is_active', true)
            ->whereDoesntHave('teachers', function ($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subjects
        ]);
    }
}
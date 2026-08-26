<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\TeacherSubjectGrade;
use Illuminate\Support\Facades\Validator;
use App\Models\StudentSubscription;
use App\LogsActivity;


class TeacherSubjectController extends Controller
{
    use LogsActivity; // ✅ استخدمنا الـ Trait

    /**
     * عرض كل المواد المضافة للمدرسين
     */
    public function index(Request $request)
    {
        $query = TeacherSubjectGrade::with(['teacher', 'subject', 'grade']);

        // فلترة حسب المدرس
        if ($request->has('teacher_id') && $request->teacher_id) {
            $query->where('teacher_id', $request->teacher_id);
        }

        // فلترة حسب المادة
        if ($request->has('subject_id') && $request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        // فلترة حسب الصف
        if ($request->has('grade_id') && $request->grade_id) {
            $query->where('grade_id', $request->grade_id);
        }

        // بحث بكود المادة
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('access_code', 'LIKE', '%' . $search . '%');
        }

        $assignments = $query->latest()->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $assignments
        ]);
    }

    /**
     * إضافة مادة لمدرس (ربط)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'grade_id' => 'required|exists:grades,id',
            'access_code' => 'required|string|unique:teacher_subject_grade,access_code|max:50',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // التأكد من إن المدرس موجود ودوره teacher
        $teacher = User::find($request->teacher_id);
        if (!$teacher || $teacher->role !== 'teacher') {
            return response()->json([
                'success' => false,
                'message' => 'المدرس غير موجود أو ليس لديه صلاحية التدريس'
            ], 422);
        }

        // التأكد من عدم تكرار الربط
        $exists = TeacherSubjectGrade::where([
            'teacher_id' => $request->teacher_id,
            'subject_id' => $request->subject_id,
            'grade_id' => $request->grade_id,
        ])->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'هذا المدرس مُضاف بالفعل لهذه المادة في هذا الصف'
            ], 422);
        }

        $assignment = TeacherSubjectGrade::create([
            'teacher_id' => $request->teacher_id,
            'subject_id' => $request->subject_id,
            'grade_id' => $request->grade_id,
            'access_code' => $request->access_code,
            'is_active' => $request->is_active ?? true,
        ]);

        // ✅ تسجيل النشاط - إضافة مادة لمدرس
        $this->logActivity(
            'إضافة مادة لمدرس',
            "تم إضافة مادة {$assignment->subject->name} للمدرس {$teacher->name} بواسطة " . auth()->user()->name,
            'create'
        );

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة المادة للمدرس بنجاح',
            'data' => $assignment->load(['teacher', 'subject', 'grade'])
        ]);
    }

    /**
     * عرض تفاصيل ربط معين
     */
    public function show($id)
    {
        $assignment = TeacherSubjectGrade::with(['teacher', 'subject', 'grade'])
            ->find($id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'الربط غير موجود'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $assignment
        ]);
    }

    /**
     * تحديث ربط (تعديل الكود فقط)
     */
    public function updateAccessCode(Request $request, $id)
    {
        $assignment = TeacherSubjectGrade::find($id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة لهذا المدرس'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'access_code' => 'required|string|unique:teacher_subject_grade,access_code,' . $id . '|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldCode = $assignment->access_code;
        $assignment->access_code = $request->access_code;
        $assignment->save();

        // ✅ تسجيل النشاط - تحديث كود المادة
        $this->logActivity(
            'تحديث كود المادة',
            "تم تحديث كود المادة من {$oldCode} إلى {$request->access_code} بواسطة " . auth()->user()->name,
            'update'
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث كود المادة بنجاح',
            'data' => $assignment,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * حذف ربط (إزالة المادة عن المدرس)
     */
    public function destroy($id)
    {
        $assignment = TeacherSubjectGrade::find($id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'الربط غير موجود'
            ], 404);
        }

        $subjectName = $assignment->subject->name;
        $teacherName = $assignment->teacher->name;

        $assignment->delete();

        // ✅ تسجيل النشاط - حذف مادة من مدرس
        $this->logActivity(
            'حذف مادة من مدرس',
            "تم حذف مادة {$subjectName} من المدرس {$teacherName} بواسطة " . auth()->user()->name,
            'delete'
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الربط بنجاح'
        ]);
    }

    /**
     * تغيير حالة الربط (تفعيل/تعطيل)
     */
    public function toggleStatus($id)
    {
        $assignment = TeacherSubjectGrade::find($id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'الربط غير موجود'
            ], 404);
        }

        $assignment->is_active = !$assignment->is_active;
        $assignment->save();

        // ✅ تسجيل النشاط - تغيير حالة المادة
        $statusText = $assignment->is_active ? 'تفعيل' : 'تعطيل';
        $this->logActivity(
            $assignment->is_active ? 'تفعيل مادة لمدرس' : 'تعطيل مادة لمدرس',
            "تم {$statusText} مادة {$assignment->subject->name} للمدرس {$assignment->teacher->name} بواسطة " . auth()->user()->name,
            'update'
        );

        return response()->json([
            'success' => true,
            'message' => $assignment->is_active ? 'تم تفعيل الربط' : 'تم تعطيل الربط',
            'data' => $assignment
        ]);
    }

    /**
     * جلب بيانات الـ Dropdowns
     */
    public function formData()
    {
        // $teachers = User::where('role', 'teacher')
        //     ->where('is_active', true)
        //     ->get(['id', 'name', 'phone']);

        $subjects = Subject::where('is_active', true)
            ->get(['id', 'name', 'code']);

        $grades = Grade::all(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data' => [
                // 'teachers' => $teachers,
                'subjects' => $subjects,
                'grades' => $grades,
            ]
        ]);
    }

    /**
     * جلب المواد والصفوف المتاحة لمدرس معين (لإضافة ربط جديد)
     */
    public function availableForTeacher($teacherId)
    {
        // المواد اللي المدرس مش مضيفها
        $existingIds = TeacherSubjectGrade::where('teacher_id', $teacherId)
            ->pluck('subject_id')
            ->toArray();

        $subjects = Subject::where('is_active', true)
            ->whereNotIn('id', $existingIds)
            ->get(['id', 'name', 'code']);

        // كل الصفوف
        $grades = Grade::all(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data' => [
                'subjects' => $subjects,
                'grades' => $grades,
            ]
        ]);
    }

    /**
     * توليد كود مادة تلقائي (فريد)
     */
    public function generateCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required|exists:users,id',
            'subject_id' => 'required|exists:subjects,id',
            'grade_id' => 'required|exists:grades,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $teacher = User::find($request->teacher_id);
        $subject = Subject::find($request->subject_id);
        $grade = Grade::find($request->grade_id);

        // تنظيف اسم المدرس
        $teacherName = $this->cleanTeacherName($teacher->name);
        
        // MATH-G1-TCH2 (مدرس رقم 2)
        $code = strtoupper($subject->code) . '-' . $grade->code . '-TCH' . $teacher->id;

        // التأكد من عدم التكرار
        $exists = TeacherSubjectGrade::where('access_code', $code)->exists();
        $counter = 1;
        while ($exists) {
            $code = $code . rand(10, 99);
            $exists = TeacherSubjectGrade::where('access_code', $code)->exists();
            $counter++;
            if ($counter > 10) break;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'access_code' => $code,
                'teacher' => $teacher->name,
                'teacher_id' => $teacher->id,
                'subject' => $subject->name,
                'grade' => $grade->name,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * تنظيف اسم المدرس
     */
    private function cleanTeacherName($name)
    {
        // إزالة الألقاب (د., دكتور, أستاذ, إلخ)
        $name = preg_replace('/^(د\.|دكتور|أستاذ|الأستاذ|د\s|دكتور\s|أ\.د\.|أستاذ\s|الأستاذ\s)/u', '', $name);
        
        // إزالة الرموز الغريبة
        $name = preg_replace('/[^a-zA-Z0-9\s\x{0600}-\x{06FF}]/u', '', $name);
        
        // إزالة المسافات الزائدة
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        
        return $name;
    }

    /**
     * تفاصيل المدرس
     */
    public function teacherDetails($id)
    {
        $teacher = User::with(['teacherSubjectGrades' => function($query) {
            $query->with(['subject', 'grade']);
        }])->find($id);

        if (!$teacher || $teacher->role !== 'teacher') {
            return response()->json([
                'success' => false,
                'message' => 'المدرس غير موجود'
            ], 404);
        }

        // إحصائيات المدرس
        $totalSubjects = $teacher->teacherSubjectGrades->count();
        $totalStudents = 0;
        $activeStudents = 0;
        
        $subjectsDetails = $teacher->teacherSubjectGrades->map(function($assignment) use (&$totalStudents, &$activeStudents) {
            $students = StudentSubscription::where('teacher_subject_grade_id', $assignment->id);
            $totalCount = $students->count();
            $activeCount = $students->where('status', 'active')
            ->whereHas('student', function ($q) {
                $q->where('is_active', true);
            })
            ->count();
            
            $totalStudents += $totalCount;
            $activeStudents += $activeCount;
            
            return [
                'id' => $assignment->id,
                'subject_name' => $assignment->subject->name,
                'grade_name' => $assignment->grade->name,
                'access_code' => $assignment->access_code,
                'total_students' => $totalCount,
                'active_students' => $activeCount,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'phone' => $teacher->phone,
                    'image' => $teacher->image,
                    'image_url' => $teacher->image_url,
                    'is_active' => $teacher->is_active,
                    'created_at' => $teacher->created_at->format('Y-m-d'),
                    'role' => $teacher->role,
                ],
                'stats' => [
                    'total_subjects' => $totalSubjects,
                    'total_students' => $totalStudents,
                    'active_students' => $activeStudents,
                ],
                'subjects' => $subjectsDetails,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب كل الطلاب المسجلين عند مدرس معين
     */
    public function teacherStudents($teacherId)
    {
        $teacher = User::find($teacherId);

        if (!$teacher || $teacher->role !== 'teacher') {
            return response()->json([
                'success' => false,
                'message' => 'المدرس غير موجود'
            ], 404);
        }

        $assignments = TeacherSubjectGrade::where('teacher_id', $teacherId)->pluck('id');
        
        $students = StudentSubscription::whereIn('teacher_subject_grade_id', $assignments)
            ->with(['student', 'teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'teacher' => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                ],
                'total_students' => $students->count(),
                'active_students' => $students->where('status', 'active')->count(),
                'students' => $students->map(function($subscription) {
                    return [
                        'id' => $subscription->student->id,
                        'name' => $subscription->student->name,
                        'phone' => $subscription->student->phone,
                        'image' => $subscription->student->image,
                        'subject' => $subscription->teacherSubjectGrade->subject->name,
                        'grade' => $subscription->teacherSubjectGrade->grade->name,
                        'status' => $subscription->status,
                        'subscribed_at' => $subscription->subscribed_at,
                        'expires_at' => $subscription->expires_at,
                    ];
                }),
            ]
        ]);
    }

    /**
     * تغيير حالة المدرس (تفعيل/تعطيل)
     */
    public function toggleTeacherStatus($id)
    {
        $teacher = User::find($id);

        if (!$teacher || $teacher->role !== 'teacher') {
            return response()->json([
                'success' => false,
                'message' => 'المدرس غير موجود'
            ], 404);
        }

        $teacher->is_active = !$teacher->is_active;
        $teacher->save();

        // ✅ تسجيل النشاط - تغيير حالة المدرس
        $statusText = $teacher->is_active ? 'تفعيل' : 'تعطيل';
        $this->logActivity(
            $teacher->is_active ? 'تفعيل مدرس' : 'تعطيل مدرس',
            "تم {$statusText} المدرس {$teacher->name} بواسطة " . auth()->user()->name,
            $teacher->is_active ? 'unban' : 'ban'
        );

        return response()->json([
            'success' => true,
            'message' => $teacher->is_active ? 'تم تفعيل المدرس' : 'تم تعطيل المدرس',
            'data' => $teacher
        ]);
    }
}
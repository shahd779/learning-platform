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

class TeacherSubjectController extends Controller
{
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

        $assignment->access_code = $request->access_code;
        $assignment->save();

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


        $assignment->delete();

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
        $teachers = User::where('role', 'teacher')
            ->where('is_active', true)
            ->get(['id', 'name', 'phone']);

        $subjects = Subject::where('is_active', true)
            ->get(['id', 'name', 'code']);

        $grades = Grade::all(['id', 'name', 'code']);

        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => $teachers,
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
    
    // ============================================
    // الطريقة 1: باستخدام ID المدرس (الأفضل)
    // ============================================
    // MATH-G1-TCH2 (مدرس رقم 2)
    $code = strtoupper($subject->code) . '-' . $grade->code . '-TCH' . $teacher->id;

    // ============================================
    // الطريقة 2: باستخدام أول 3 حروف + رقم عشوائي
    // ============================================
    // MATH-G1-ALI78
    // $random = rand(10, 99);
    // $code = strtoupper($subject->code) . '-' . $grade->code . '-' . 
    //         strtoupper($teacherName) . $random;

    // ============================================
    // الطريقة 3: باستخدام الاسم الكامل + رقم تسلسلي
    // ============================================
    // MATH-G1-ALIHASSAN
    // $fullName = strtoupper(str_replace(' ', '', $teacherName));
    // $code = strtoupper($subject->code) . '-' . $grade->code . '-' . $fullName;

    // ============================================
    // الطريقة 4: عشوائي بالكامل (غير مرتبط بالاسم)
    // ============================================
    // MATH-G1-X7K9L
    // $random = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5));
    // $code = strtoupper($subject->code) . '-' . $grade->code . '-' . $random;

    // التأكد من عدم التكرار
    $exists = TeacherSubjectGrade::where('access_code', $code)->exists();
    $counter = 1;
    while ($exists) {
        // لو الكود موجود، نضيف رقم عشوائي في الآخر
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
     * جلب كل طلاب مادة معينة لمدرس معين
     */
    public function students($id)
    {
        $assignment = TeacherSubjectGrade::with(['subscriptions.student'])
            ->find($id);

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'الربط غير موجود'
            ], 404);
        }

        $students = $assignment->subscriptions()
            ->with('student')
            ->where('status', 'active')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $students
        ]);
    }

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
     * جلب طلاب مادة معينة لمدرس معين
     */
    public function subjectStudents($teacherId, $subjectId)
    {
        $assignment = TeacherSubjectGrade::where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'المادة غير موجودة لهذا المدرس'
            ], 404);
        }

        $students = StudentSubscription::where('teacher_subject_grade_id', $assignment->id)
            ->with('student')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'subject' => $assignment->subject->name,
                'grade' => $assignment->grade->name,
                'access_code' => $assignment->access_code,
                'total_students' => $students->count(),
                'active_students' => $students->where('status', 'active')->count(),
                'students' => $students->map(function($subscription) {
                    return [
                        'id' => $subscription->student->id,
                        'name' => $subscription->student->name,
                        'phone' => $subscription->student->phone,
                        'image' => $subscription->student->image,
                        'status' => $subscription->status,
                        'subscribed_at' => $subscription->subscribed_at,
                        'expires_at' => $subscription->expires_at,
                    ];
                }),
            ]
        ]);
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

        return response()->json([
            'success' => true,
            'message' => $teacher->is_active ? 'تم تفعيل المدرس' : 'تم تعطيل المدرس',
            'data' => $teacher
        ]);
    }
}
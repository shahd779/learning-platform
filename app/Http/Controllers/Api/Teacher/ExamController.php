<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Exam;
use App\Models\TeacherSubjectGrade;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\QuestionBank;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    /**
     * عرض كل الاختبارات
     */
    public function index(Request $request)
    {
        $teacher = auth()->user();

        $query = Exam::where('teacher_id', $teacher->id)
            ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade']);

        // فلترة حسب المادة
        if ($request->has('subject_id') && $request->subject_id && $request->subject_id !== 'all') {
            $query->whereHas('teacherSubjectGrade', function($q) use ($request) {
                $q->where('subject_id', $request->subject_id);
            });
        }

        // فلترة حسب الصف
        if ($request->has('grade_id') && $request->grade_id && $request->grade_id !== 'all') {
            $query->whereHas('teacherSubjectGrade', function($q) use ($request) {
                $q->where('grade_id', $request->grade_id);
            });
        }

        // فلترة حسب الحالة
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // بحث
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('title', 'LIKE', '%' . $search . '%');
        }

        $exams = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);

        // فك تشفير الأسئلة لكل اختبار
        $exams->getCollection()->transform(function($exam) {
            $exam->questions = json_decode($exam->questions, true);
            return $exam;
        });

        return response()->json([
            'success' => true,
            'data' => $exams,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب خيارات الفلترة (الصفوف والمواد للمدرس)
     */
    public function filterOptions()
    {
        $teacher = auth()->user();

        $teacherSubjects = TeacherSubjectGrade::where('teacher_id', $teacher->id)
            ->with(['subject', 'grade'])
            ->get();

        $grades = $teacherSubjects->pluck('grade')->unique('id')->values()->map(function($grade) {
            return [
                'id' => $grade->id,
                'name' => $grade->name,
            ];
        });

        $subjects = $teacherSubjects->pluck('subject')->unique('id')->values()->map(function($subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
            ];
        });

        $statuses = [
            ['value' => 'all', 'label' => 'الكل'],
            ['value' => 'draft', 'label' => 'مسودة'],
            ['value' => 'published', 'label' => 'منشور'],
            ['value' => 'scheduled', 'label' => 'مجدول'],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'grades' => $grades,
                'subjects' => $subjects,
                'statuses' => $statuses,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب بيانات إضافة اختبار (الصفوف والمواد)
     */
    public function createFormData()
    {
        $teacher = auth()->user();

        $teacherSubjects = TeacherSubjectGrade::where('teacher_id', $teacher->id)
            ->with(['subject', 'grade'])
            ->get();

        $grades = $teacherSubjects->pluck('grade')->unique('id')->values()->map(function($grade) {
            return [
                'id' => $grade->id,
                'name' => $grade->name,
            ];
        });

        $subjects = $teacherSubjects->pluck('subject')->unique('id')->values()->map(function($subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
            ];
        });

        // ✅ مستويات الصعوبة للأسئلة
        $difficultyLevels = [
            ['value' => 'easy', 'label' => 'سهل', 'color' => 'green'],
            ['value' => 'medium', 'label' => 'متوسط', 'color' => 'yellow'],
            ['value' => 'hard', 'label' => 'صعب', 'color' => 'red'],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'grades' => $grades,
                'subjects' => $subjects,
                'difficulty_levels' => $difficultyLevels,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * إنشاء اختبار جديد
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_subject_grade_id' => 'required|exists:teacher_subject_grade,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published,scheduled',
            'visibility' => 'nullable|in:all,limited',
            'start_at' => 'required_if:status,scheduled|nullable|date|after:now',
            'questions' => 'nullable|array',
            'questions.*.type' => 'required|in:multiple_choice,true_false,essay',
            'questions.*.question' => 'required|string',
            'questions.*.marks' => 'required|integer|min:1',
            'questions.*.difficulty' => 'required|in:easy,medium,hard',
            'questions.*.options' => 'required_if:questions.*.type,multiple_choice|array|min:2',
            'questions.*.correct_answer' => 'required_if:questions.*.type,multiple_choice,true_false',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // جلب المادة للتأكد من إنها للمدرس
        $teacherSubject = TeacherSubjectGrade::where('id', $request->teacher_subject_grade_id)
            ->where('teacher_id', auth()->id())
            ->first();

        if (!$teacherSubject) {
            return response()->json([
                'success' => false,
                'message' => 'هذه المادة غير مسجلة لك'
            ], 403);
        }

        // حساب الدرجة الكلية
        $totalMarks = 0;
        $questions = $request->questions ?? [];
        foreach ($questions as $question) {
            $totalMarks += $question['marks'] ?? 0;
        }

        // تحديد تاريخ النشر
        $startAt = null;
        if ($request->status === 'scheduled') {
            $startAt = $request->start_at;
        } elseif ($request->status === 'published') {
            $startAt = now();
        }

        $exam = Exam::create([
            'teacher_subject_grade_id' => $request->teacher_subject_grade_id,
            'teacher_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'duration_minutes' => $request->duration_minutes,
            'total_marks' => $totalMarks,
            'status' => $request->status,
            'visibility' => $request->visibility ?? 'all',
            'start_at' => $startAt,
            'questions' => json_encode($questions),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الاختبار بنجاح',
            'data' => $exam->load(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade']),
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * عرض اختبار معين
     */
    public function show($id)
    {
        $teacher = auth()->user();

        $exam = Exam::where('teacher_id', $teacher->id)
            ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
            ->find($id);

        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'الاختبار غير موجود'
            ], 404);
        }

        // فك تشفير الأسئلة
        $exam->questions = json_decode($exam->questions, true);

        return response()->json([
            'success' => true,
            'data' => $exam,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * تحديث اختبار
     */
    public function update(Request $request, $id)
    {
        $teacher = auth()->user();

        $exam = Exam::where('teacher_id', $teacher->id)->find($id);

        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'الاختبار غير موجود'
            ], 404);
        }

        // منع تعديل الاختبارات المنشورة (اختياري)
        if ($exam->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن تعديل اختبار منشور'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'status' => 'sometimes|in:draft,published,scheduled',
            'visibility' => 'nullable|in:all,limited',
            'start_at' => 'required_if:status,scheduled|nullable|date|after:now',
            'questions' => 'nullable|array',
            'questions.*.type' => 'required|in:multiple_choice,true_false,essay',
            'questions.*.question' => 'required|string',
            'questions.*.marks' => 'required|integer|min:1',
            'questions.*.difficulty' => 'required|in:easy,medium,hard',
            'questions.*.options' => 'required_if:questions.*.type,multiple_choice|array|min:2',
            'questions.*.correct_answer' => 'required_if:questions.*.type,multiple_choice,true_false',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['title', 'description', 'duration_minutes', 'status', 'visibility']);

        // حساب الدرجة الكلية
        if ($request->has('questions')) {
            $questions = $request->questions;
            $totalMarks = 0;
            foreach ($questions as $question) {
                $totalMarks += $question['marks'] ?? 0;
            }
            $data['total_marks'] = $totalMarks;
            $data['questions'] = json_encode($questions);
        }

        // تحديث تاريخ النشر
        if ($request->has('status')) {
            if ($request->status === 'scheduled' && $request->has('start_at')) {
                $data['start_at'] = $request->start_at;
            } elseif ($request->status === 'published') {
                $data['start_at'] = now();
            }
        }

        $exam->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الاختبار بنجاح',
            'data' => $exam->fresh(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade']),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * حذف اختبار
     */
    public function destroy($id)
    {
        $teacher = auth()->user();

        $exam = Exam::where('teacher_id', $teacher->id)->find($id);

        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'الاختبار غير موجود'
            ], 404);
        }

        $exam->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الاختبار بنجاح',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * تغيير حالة الاختبار (نشر / مسودة)
     */
    public function toggleStatus($id)
    {
        $teacher = auth()->user();

        $exam = Exam::where('teacher_id', $teacher->id)->find($id);

        if (!$exam) {
            return response()->json([
                'success' => false,
                'message' => 'الاختبار غير موجود'
            ], 404);
        }

        $newStatus = $exam->status === 'draft' ? 'published' : 'draft';

        $exam->update([
            'status' => $newStatus,
            'start_at' => $newStatus === 'published' ? now() : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $newStatus === 'published' ? 'تم نشر الاختبار' : 'تم حفظ الاختبار كمسودة',
            'data' => $exam,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب إحصائيات الاختبارات
     */
    public function stats()
    {
        $teacher = auth()->user();

        $totalExams = Exam::where('teacher_id', $teacher->id)->count();
        $draftExams = Exam::where('teacher_id', $teacher->id)->where('status', 'draft')->count();
        $publishedExams = Exam::where('teacher_id', $teacher->id)->where('status', 'published')->count();
        $scheduledExams = Exam::where('teacher_id', $teacher->id)->where('status', 'scheduled')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalExams,
                'draft' => $draftExams,
                'published' => $publishedExams,
                'scheduled' => $scheduledExams,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    // ========================================
    // ✅ بنك الأسئلة (Question Bank)
    // ========================================

    /**
     * حفظ سؤال في بنك الأسئلة
     */
    public function saveToBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'grade_id' => 'required|exists:grades,id',
            'type' => 'required|in:multiple_choice,true_false,essay',
            'question' => 'required|string',
            'marks' => 'required|integer|min:1',
            'difficulty' => 'required|in:easy,medium,hard',
            'options' => 'required_if:type,multiple_choice|array|min:2',
            'correct_answer' => 'required_if:type,multiple_choice,true_false',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $teacher = auth()->user();

        // التحقق من أن المدرس بيدرس هذه المادة والصف
        $teacherSubject = TeacherSubjectGrade::where('teacher_id', $teacher->id)
            ->where('subject_id', $request->subject_id)
            ->where('grade_id', $request->grade_id)
            ->first();

        if (!$teacherSubject) {
            return response()->json([
                'success' => false,
                'message' => 'هذه المادة غير مسجلة لك'
            ], 403);
        }

        $question = QuestionBank::create([
            'teacher_id' => $teacher->id,
            'subject_id' => $request->subject_id,
            'grade_id' => $request->grade_id,
            'type' => $request->type,
            'question' => $request->question,
            'marks' => $request->marks,
            'difficulty' => $request->difficulty,
            'options' => $request->type === 'multiple_choice' ? json_encode($request->options) : null,
            'correct_answer' => in_array($request->type, ['multiple_choice', 'true_false']) ? $request->correct_answer : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ السؤال في بنك الأسئلة بنجاح',
            'data' => $question,
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب الأسئلة من بنك الأسئلة (لإضافتها للاختبار)
     */
    public function getFromBank(Request $request)
    {
        $teacher = auth()->user();

        $query = QuestionBank::where('teacher_id', $teacher->id)
            ->where('is_active', true);

        // فلترة حسب المادة
        if ($request->has('subject_id') && $request->subject_id && $request->subject_id !== 'all') {
            $query->where('subject_id', $request->subject_id);
        }

        // فلترة حسب الصف
        if ($request->has('grade_id') && $request->grade_id && $request->grade_id !== 'all') {
            $query->where('grade_id', $request->grade_id);
        }

        // فلترة حسب نوع السؤال
        if ($request->has('type') && $request->type && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // ✅ فلترة حسب مستوى الصعوبة
        if ($request->has('difficulty') && $request->difficulty && $request->difficulty !== 'all') {
            $query->where('difficulty', $request->difficulty);
        }

        // بحث
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('question', 'LIKE', '%' . $search . '%');
        }

        $questions = $query->paginate($request->per_page ?? 20);

        // فك تشفير options
        $questions->getCollection()->transform(function($item) {
            if ($item->options) {
                $item->options = json_decode($item->options, true);
            }
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $questions,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * حذف سؤال من بنك الأسئلة
     */
    public function deleteFromBank($id)
    {
        $teacher = auth()->user();

        $question = QuestionBank::where('teacher_id', $teacher->id)->find($id);

        if (!$question) {
            return response()->json([
                'success' => false,
                'message' => 'السؤال غير موجود'
            ], 404);
        }

        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف السؤال من بنك الأسئلة',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * إنشاء اختبار من بنك الأسئلة
     */
    public function createFromBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_subject_grade_id' => 'required|exists:teacher_subject_grade,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:1',
            'status' => 'required|in:draft,published,scheduled',
            'visibility' => 'nullable|in:all,limited',
            'start_at' => 'required_if:status,scheduled|nullable|date|after:now',
            'question_ids' => 'required|array|min:1',
            'question_ids.*' => 'exists:question_bank,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // جلب المادة للتأكد
        $teacherSubject = TeacherSubjectGrade::where('id', $request->teacher_subject_grade_id)
            ->where('teacher_id', auth()->id())
            ->first();

        if (!$teacherSubject) {
            return response()->json([
                'success' => false,
                'message' => 'هذه المادة غير مسجلة لك'
            ], 403);
        }

        // جلب الأسئلة من بنك الأسئلة
        $questions = QuestionBank::whereIn('id', $request->question_ids)
            ->where('teacher_id', auth()->id())
            ->get();

        if ($questions->count() !== count($request->question_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'بعض الأسئلة غير موجودة أو لا تخصك'
            ], 403);
        }

        // تجهيز الأسئلة للاختبار
        $examQuestions = $questions->map(function($q) {
            $data = [
                'type' => $q->type,
                'question' => $q->question,
                'marks' => $q->marks,
                'difficulty' => $q->difficulty,
            ];

            if ($q->type === 'multiple_choice') {
                $data['options'] = json_decode($q->options, true);
                $data['correct_answer'] = $q->correct_answer;
            } elseif ($q->type === 'true_false') {
                $data['correct_answer'] = $q->correct_answer;
            }

            return $data;
        })->toArray();

        // حساب الدرجة الكلية
        $totalMarks = $questions->sum('marks');

        // تحديد تاريخ النشر
        $startAt = null;
        if ($request->status === 'scheduled') {
            $startAt = $request->start_at;
        } elseif ($request->status === 'published') {
            $startAt = now();
        }

        $exam = Exam::create([
            'teacher_subject_grade_id' => $request->teacher_subject_grade_id,
            'teacher_id' => auth()->id(),
            'title' => $request->title,
            'description' => $request->description,
            'duration_minutes' => $request->duration_minutes,
            'total_marks' => $totalMarks,
            'status' => $request->status,
            'visibility' => $request->visibility ?? 'all',
            'start_at' => $startAt,
            'questions' => json_encode($examQuestions),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء الاختبار من بنك الأسئلة بنجاح',
            'data' => $exam->load(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade']),
        ], 201, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب مستويات الصعوبة للأسئلة
     */
    public function difficultyOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['value' => 'easy', 'label' => 'سهل', 'color' => '#10B981'],
                ['value' => 'medium', 'label' => 'متوسط', 'color' => '#F59E0B'],
                ['value' => 'hard', 'label' => 'صعب', 'color' => '#EF4444'],
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * جلب إحصائيات بنك الأسئلة
     */
    public function bankStats()
    {
        $teacher = auth()->user();

        $totalQuestions = QuestionBank::where('teacher_id', $teacher->id)->count();
        $easyQuestions = QuestionBank::where('teacher_id', $teacher->id)->where('difficulty', 'easy')->count();
        $mediumQuestions = QuestionBank::where('teacher_id', $teacher->id)->where('difficulty', 'medium')->count();
        $hardQuestions = QuestionBank::where('teacher_id', $teacher->id)->where('difficulty', 'hard')->count();

        $typeStats = [
            'multiple_choice' => QuestionBank::where('teacher_id', $teacher->id)->where('type', 'multiple_choice')->count(),
            'true_false' => QuestionBank::where('teacher_id', $teacher->id)->where('type', 'true_false')->count(),
            'essay' => QuestionBank::where('teacher_id', $teacher->id)->where('type', 'essay')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalQuestions,
                'by_difficulty' => [
                    'easy' => $easyQuestions,
                    'medium' => $mediumQuestions,
                    'hard' => $hardQuestions,
                ],
                'by_type' => $typeStats,
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
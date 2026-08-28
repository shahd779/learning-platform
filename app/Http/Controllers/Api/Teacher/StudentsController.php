<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\File;
use App\Models\FileDownload;

use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;
use App\Models\Video;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\VideoProgress;
use App\Models\AssignmentSubmission;
use App\Models\ExamResult;
use Illuminate\Support\Facades\DB;

class StudentsController extends Controller
{


/**
 * جلب قائمة الطلاب مع تفاصيلهم
 */
public function students(Request $request)
{
    $teacher = auth()->user();
    
    // جلب كل المواد اللي المدرس بيدرسها
    $teacherSubjects = TeacherSubjectGrade::where('teacher_id', $teacher->id)
        ->with(['subject', 'grade'])
        ->get();
    
    $subjectIds = $teacherSubjects->pluck('id');
    
    // ✅ فلترة حسب المادة
    if ($request->has('subject_id') && $request->subject_id && $request->subject_id !== 'all') {
        $teacherSubjects = $teacherSubjects->filter(function($tsg) use ($request) {
            return $tsg->subject_id == $request->subject_id;
        });
        $subjectIds = $teacherSubjects->pluck('id');
    }
    
    // ✅ فلترة حسب الصف
    if ($request->has('grade_id') && $request->grade_id && $request->grade_id !== 'all') {
        $teacherSubjects = $teacherSubjects->filter(function($tsg) use ($request) {
            return $tsg->grade_id == $request->grade_id;
        });
        $subjectIds = $teacherSubjects->pluck('id');
    }
    
    // جلب اشتراكات الطلاب في المواد المحددة
    $subscriptions = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIds)
        ->with(['student', 'teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
        ->get();
    
    // ========================================
    // ✅ الجزء الأول: الإحصائيات (Stats)
    // ========================================
    $totalStudents = $subscriptions->pluck('student_id')->unique()->count();
    
    $activeStudents = $subscriptions->filter(function($sub) {
        return $sub->student->is_active == true 
            && $sub->status === 'active' 
            && $sub->is_banned == false;
    })->pluck('student_id')->unique()->count();
    
    $inactiveStudents = $subscriptions->filter(function($sub) {
        return $sub->student->is_active == false 
            || $sub->status === 'expired' 
            || $sub->is_banned == true;
    })->pluck('student_id')->unique()->count();
    
    // تفاصيل المواد
    $subjectsDetails = $teacherSubjects->map(function($tsg) use ($subscriptions) {
        $subSubscriptions = $subscriptions->filter(function($sub) use ($tsg) {
            return $sub->teacher_subject_grade_id == $tsg->id;
        });
        
        return [
            'id' => $tsg->id,
            'subject' => $tsg->subject->name,
            'grade' => $tsg->grade->name,
            'access_code' => $tsg->access_code,
            'total_students' => $subSubscriptions->pluck('student_id')->unique()->count(),
            'active_students' => $subSubscriptions->filter(function($sub) {
                return $sub->student->is_active == true 
                    && $sub->status === 'active' 
                    && $sub->is_banned == false;
            })->pluck('student_id')->unique()->count(),
        ];
    });
    
    // ========================================
    // ✅ الجزء الثاني: قائمة الطلاب (Students)
    // ========================================
    
    $subjectId = $request->subject_id;
    $gradeId = $request->grade_id;
    
    $query = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIds)
        ->with(['student', 'teacherSubjectGrade.subject', 'teacherSubjectGrade.grade']);
    
    if ($subjectId && $subjectId !== 'all') {
        $query->whereHas('teacherSubjectGrade', function($q) use ($subjectId) {
            $q->where('subject_id', $subjectId);
        });
    }
    
    if ($gradeId && $gradeId !== 'all') {
        $query->whereHas('teacherSubjectGrade', function($q) use ($gradeId) {
            $q->where('grade_id', $gradeId);
        });
    }
    
    if ($request->has('search') && $request->search) {
        $search = $request->search;
        $query->whereHas('student', function($q) use ($search) {
            $q->where('name', 'LIKE', '%' . $search . '%')
              ->orWhere('phone', 'LIKE', '%' . $search . '%');
        });
    }
    
    $studentsSubscriptions = $query->get();
    
    // تجميع الطلاب
    $students = $studentsSubscriptions->groupBy('student_id')->map(function($subs) use ($teacher) {
        $student = $subs->first()->student;
        
        // ✅ جلب المواد اللي الطالب مشترك فيها مع هذا المدرس
        $studentSubjectIds = $subs->pluck('teacher_subject_grade_id')->toArray();
        
        // جلب كل المواد اللي الطالب مشترك فيها مع هذا المدرس
        $studentSubjects = $subs->map(function($sub) {
            return [
                'subject' => $sub->teacherSubjectGrade->subject->name ?? 'محذوف',
                'grade' => $sub->teacherSubjectGrade->grade->name ?? 'محذوف',
                'status' => $sub->status,
                'is_banned' => $sub->is_banned,
            ];
        });
        
        // ========================================
        // ✅ حساب نسبة التقدم (كل المحتوى)
        // ========================================
        
        // 1️⃣ الفيديوهات
        $videos = Video::where('teacher_id', $teacher->id)
            ->where('status', 'approved')
            ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
            ->get();
        
        $totalVideos = $videos->count();
        $watchedVideos = VideoProgress::where('user_id', $student->id)
            ->whereIn('video_id', $videos->pluck('id'))
            ->where('is_completed', true)
            ->count();
        
        // 2️⃣ الواجبات
        $assignments = Assignment::where('teacher_id', $teacher->id)
            ->where('status', 'published')
            ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
            ->get();
        
        $totalAssignments = $assignments->count();
        $submittedAssignments = AssignmentSubmission::where('student_id', $student->id)
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->where('status', 'submitted')
            ->count();
        
        // 3️⃣ الامتحانات
        $exams = Exam::where('teacher_id', $teacher->id)
            ->where('status', 'published')
            ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
            ->get();
        
        $totalExams = $exams->count();
        $completedExams = ExamResult::where('student_id', $student->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->where('status', 'completed')
            ->count();
        
        // 4️⃣ ✅ الملفات (Files)
        $files = File::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
            ->get();
        
        $totalFiles = $files->count();
        
     // 4️⃣ الملفات (Files) - مع حساب التحميلات
    $files = File::where('teacher_id', $teacher->id)
        ->where('is_active', true)
        ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
        ->get();

    $totalFiles = $files->count();

        // ✅ عدد الملفات التي قام الطالب بتحميلها
        $downloadedFiles = FileDownload::where('student_id', $student->id)
            ->whereIn('file_id', $files->pluck('id'))
            ->count();
        
        // ✅ حساب النسبة الكلية
        $totalItems = $totalVideos + $totalAssignments + $totalExams + $totalFiles;
        $completedItems = $watchedVideos + $submittedAssignments + $completedExams + $downloadedFiles;
        
        $progressPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
        
        // ✅ المحتوى المكتمل (فيديوهات فقط)
        $completedContent = $totalVideos > 0 ? "{$watchedVideos} / {$totalVideos}" : "0 / 0";
        
        // حالة الطالب
        $isActive = $student->is_active;
        $hasActiveSubscription = $subs->contains(function($sub) {
            return $sub->status === 'active' && $sub->is_banned == false;
        });
        
        $status = '';
        $statusType = '';
        
        if (!$isActive) {
            $status = 'حساب موقوف';
            $statusType = 'blocked';
        } elseif ($subs->contains('is_banned', true)) {
            $status = 'محظور';
            $statusType = 'banned';
        } elseif (!$hasActiveSubscription) {
            $status = 'اشتراك منتهي';
            $statusType = 'expired';
        } else {
            $status = 'نشط';
            $statusType = 'active';
        }
        
        return [
            'id' => $student->id,
            'name' => $student->name,
            'phone' => $student->phone,
            'image' => $student->image,
            'image_url' => $student->image_url,
            'subjects' => $studentSubjects,
            'total_subjects' => $studentSubjects->count(),
            
            // ✅ نسبة التقدم (كل المحتوى)
            'progress_percentage' => $progressPercentage,
            
            // ✅ تفاصيل الفيديوهات (للمحتوى المكتمل)
            'watched_videos' => $watchedVideos,
            'total_videos' => $totalVideos,
            'completed_content' => $completedContent,
            
            // ✅ تفاصيل الواجبات
            'submitted_assignments' => $submittedAssignments,
            'total_assignments' => $totalAssignments,
            
            // ✅ تفاصيل الامتحانات
            'completed_exams' => $completedExams,
            'total_exams' => $totalExams,
            
            // ✅ تفاصيل الملفات
            'downloaded_files' => $downloadedFiles,
            'total_files' => $totalFiles,
            
            // ✅ تفاصيل إضافية
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            
            'status' => $status,
            'status_type' => $statusType,
        ];
    })->values();
    
    // ترتيب حسب الاسم
    $students = $students->sortBy('name')->values();
    
    // Pagination يدوي
    $perPage = $request->per_page ?? 10;
    $currentPage = $request->page ?? 1;
    $total = $students->count();
    $paginated = $students->slice(($currentPage - 1) * $perPage, $perPage)->values();
    
    return response()->json([
        'success' => true,
        'data' => [
            'stats' => [
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'inactive_students' => $inactiveStudents,
            ],
            'subjects' => $subjectsDetails,
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
            ],
            'filters' => [
                'subject_id' => $request->subject_id,
                'grade_id' => $request->grade_id,
                'search' => $request->search,
            ],
            'students' => [
                'current_page' => $currentPage,
                'data' => $paginated,
                'total' => $total,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}
    
 /**
 * جلب تفاصيل طالب معين (صفحة منفصلة)
 */
public function studentDetails($studentId)
{
    $teacher = auth()->user();
    
    $student = User::where('role', 'student')->find($studentId);
    
    if (!$student) {
        return response()->json([
            'success' => false,
            'message' => 'الطالب غير موجود'
        ], 404);
    }
    
    // جلب اشتراكات الطالب مع هذا المدرس
    $subscriptions = StudentSubscription::where('student_id', $studentId)
        ->whereHas('teacherSubjectGrade', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })
        ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
        ->get();
    
    if ($subscriptions->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'الطالب غير مشترك معك'
        ], 404);
    }
    
    // ✅ جلب المواد اللي الطالب مشترك فيها مع هذا المدرس
    $studentSubjectIds = $subscriptions->pluck('teacher_subject_grade_id')->toArray();
    
    // ========================================
    // 1️⃣ الفيديوهات
    // ========================================
    $videos = Video::where('teacher_id', $teacher->id)
        ->where('status', 'approved')
        ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
        ->get();
    
    $totalVideos = $videos->count();
    $videoProgress = VideoProgress::where('user_id', $studentId)
        ->whereIn('video_id', $videos->pluck('id'))
        ->get();
    
    $watchedVideos = $videoProgress->where('is_completed', true)->count();
    
    // ========================================
    // 2️⃣ الواجبات
    // ========================================
    $assignments = Assignment::where('teacher_id', $teacher->id)
        ->where('status', 'published')
        ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
        ->get();
    
    $totalAssignments = $assignments->count();
    $submittedAssignments = AssignmentSubmission::where('student_id', $studentId)
        ->whereIn('assignment_id', $assignments->pluck('id'))
        ->where('status', 'submitted')
        ->count();
    
    // ========================================
    // 3️⃣ الامتحانات
    // ========================================
    $exams = Exam::where('teacher_id', $teacher->id)
        ->where('status', 'published')
        ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
        ->get();
    
    $totalExams = $exams->count();
    $completedExams = ExamResult::where('student_id', $studentId)
        ->whereIn('exam_id', $exams->pluck('id'))
        ->where('status', 'completed')
        ->count();
    
    // ========================================
    // 4️⃣ الملفات
    // ========================================
    $files = File::where('teacher_id', $teacher->id)
        ->where('is_active', true)
        ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
        ->get();
    
    $totalFiles = $files->count();
    $downloadedFiles = FileDownload::where('student_id', $studentId)
        ->whereIn('file_id', $files->pluck('id'))
        ->count();
    
    // ========================================
    // ✅ حساب النسبة الكلية
    // ========================================
    $totalItems = $totalVideos + $totalAssignments + $totalExams + $totalFiles;
    $completedItems = $watchedVideos + $submittedAssignments + $completedExams + $downloadedFiles;
    
    $progressPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
    
    // ========================================
    // ✅ تفاصيل تقدم الفيديوهات
    // ========================================
    $videoProgressDetails = $videoProgress->map(function($vp) {
        return [
            'video_id' => $vp->video_id,
            'video_title' => $vp->video->title ?? '',
            'progress_percentage' => $vp->progress_percentage,
            'is_completed' => $vp->is_completed,
        ];
    });
    
    // ========================================
    // ✅ تفاصيل الواجبات المسلمة
    // ========================================
    $assignmentSubmissions = AssignmentSubmission::where('student_id', $studentId)
        ->whereIn('assignment_id', $assignments->pluck('id'))
        ->with(['assignment'])
        ->get();
    
    // ========================================
    // ✅ تفاصيل الامتحانات
    // ========================================
    $examResults = ExamResult::where('student_id', $studentId)
        ->whereIn('exam_id', $exams->pluck('id'))
        ->with(['exam'])
        ->get();
    
    // ========================================
    // ✅ تفاصيل تحميلات الملفات
    // ========================================
    $fileDownloads = FileDownload::where('student_id', $studentId)
        ->whereIn('file_id', $files->pluck('id'))
        ->with(['file'])
        ->get();
    
    return response()->json([
        'success' => true,
        'data' => [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'phone' => $student->phone,
                'image' => $student->image,
                'image_url' => $student->image_url,
                'is_active' => $student->is_active,
                'created_at' => $student->created_at->format('Y-m-d'),
            ],
            'subscriptions' => $subscriptions->map(function($sub) {
                return [
                    'id' => $sub->id,
                    'subject' => $sub->teacherSubjectGrade->subject->name,
                    'grade' => $sub->teacherSubjectGrade->grade->name,
                    'access_code' => $sub->teacherSubjectGrade->access_code,
                    'status' => $sub->status,
                    'is_banned' => $sub->is_banned,
                    'subscribed_at' => $sub->subscribed_at ? $sub->subscribed_at->format('Y-m-d') : null,
                    'expires_at' => $sub->expires_at ? $sub->expires_at->format('Y-m-d') : null,
                ];
            }),
            
            // ✅ نسبة التقدم الكلية
            'progress' => [
                'total_items' => $totalItems,
                'completed_items' => $completedItems,
                'progress_percentage' => $progressPercentage,
            ],
            
            // ✅ تفاصيل الفيديوهات
            'videos' => [
                'total' => $totalVideos,
                'watched' => $watchedVideos,
                'completed_content' => "{$watchedVideos} / {$totalVideos}",
                'progress_percentage' => $totalVideos > 0 ? round(($watchedVideos / $totalVideos) * 100) : 0,
               
            ],
            
            // ✅ تفاصيل الواجبات
            'assignments' => [
                'total' => $totalAssignments,
                'submitted' => $submittedAssignments,
                'completed_content' => "{$submittedAssignments} / {$totalAssignments}",
                'progress_percentage' => $totalAssignments > 0 ? round(($submittedAssignments / $totalAssignments) * 100) : 0,
                
            ],
            
            // ✅ تفاصيل الامتحانات
            'exams' => [
                'total' => $totalExams,
                'completed' => $completedExams,
                'completed_content' => "{$completedExams} / {$totalExams}",
                'progress_percentage' => $totalExams > 0 ? round(($completedExams / $totalExams) * 100) : 0,
                
            ],
            
            // ✅ تفاصيل الملفات
            'files' => [
                'total' => $totalFiles,
                'downloaded' => $downloadedFiles,
                'completed_content' => "{$downloadedFiles} / {$totalFiles}",
                'progress_percentage' => $totalFiles > 0 ? round(($downloadedFiles / $totalFiles) * 100) : 0,
            ],
        ]
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
        
        $grades = $teacherSubjects->map(function($tsg) {
            return $tsg->grade;
        })->unique('id')->values();
        
        $subjects = $teacherSubjects->map(function($tsg) {
            return $tsg->subject;
        })->unique('id')->values();
        
        return response()->json([
            'success' => true,
            'data' => [
                'grades' => $grades->map(function($grade) {
                    return [
                        'id' => $grade->id,
                        'name' => $grade->name,
                        'code' => $grade->code,
                    ];
                }),
                'subjects' => $subjects->map(function($subject) {
                    return [
                        'id' => $subject->id,
                        'name' => $subject->name,
                        'code' => $subject->code,
                    ];
                }),
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }



    /**
 * جلب نتائج طالب معين (قابلة للتغير في عدد النتائج)
 */
public function studentResults(Request $request, $studentId)
{
    $teacher = auth()->user();
    
    $student = User::where('role', 'student')->find($studentId);
    
    if (!$student) {
        return response()->json([
            'success' => false,
            'message' => 'الطالب غير موجود'
        ], 404);
    }
    
    // ✅ جلب اشتراكات الطالب مع هذا المدرس
    $subscriptions = StudentSubscription::where('student_id', $studentId)
        ->whereHas('teacherSubjectGrade', function($q) use ($teacher) {
            $q->where('teacher_id', $teacher->id);
        })
        ->with(['teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
        ->get();
    
    if ($subscriptions->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'الطالب غير مشترك معك'
        ], 404);
    }
    
    // ✅ جلب المواد اللي الطالب مشترك فيها مع هذا المدرس
    $studentSubjectIds = $subscriptions->pluck('teacher_subject_grade_id')->toArray();
    
    // ✅ عدد النتائج المطلوب (قابل للتغيير من الـ Request)
    $limit = $request->has('limit') ? (int)$request->limit : 10;
    
    // منع القيم الغريبة
    if ($limit < 1) $limit = 1;
    if ($limit > 100) $limit = 100;
    
    // ========================================
    // 1️⃣ نتائج الامتحانات
    // ========================================
    $exams = Exam::where('teacher_id', $teacher->id)
        ->where('status', 'published')
        ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
        ->pluck('id');
    
    $examResults = ExamResult::where('student_id', $studentId)
        ->whereIn('exam_id', $exams)
        ->where('status', 'completed')
        ->with(['exam'])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
    
    // ========================================
    // 2️⃣ نتائج الواجبات
    // ========================================
    $assignments = Assignment::where('teacher_id', $teacher->id)
        ->where('status', 'published')
        ->whereIn('teacher_subject_grade_id', $studentSubjectIds)
        ->pluck('id');
    
    $assignmentResults = AssignmentSubmission::where('student_id', $studentId)
        ->whereIn('assignment_id', $assignments)
        ->where('status', 'graded')
        ->with(['assignment'])
        ->orderBy('created_at', 'desc')
        ->limit($limit)
        ->get();
    
    // ========================================
    // 3️⃣ دمج النتائج وترتيبها
    // ========================================
    $results = collect();
    
    // إضافة نتائج الامتحانات
    foreach ($examResults as $result) {
        $results->push([
            'id' => $result->id,
            'type' => 'exam',
            'type_label' => 'اختبار',
            'title' => $result->exam->title ?? 'اختبار',
            'score' => $result->score ?? 0,
            'total' => $result->total ?? 100,
            'percentage' => $result->percentage ?? 0,
            'date' => $result->completed_at ? $result->completed_at->format('Y-m-d') : $result->created_at->format('Y-m-d'),
            'created_at' => $result->completed_at ?? $result->created_at,
        ]);
    }
    
    // إضافة نتائج الواجبات
    foreach ($assignmentResults as $result) {
        $results->push([
            'id' => $result->id,
            'type' => 'assignment',
            'type_label' => 'واجب',
            'title' => $result->assignment->title ?? 'واجب',
            'score' => $result->grade ?? 0,
            'total' => $result->assignment->total_marks ?? 100,
            'percentage' => $result->grade ? round(($result->grade / ($result->assignment->total_marks ?? 100)) * 100) : 0,
            'date' => $result->submitted_at ? $result->submitted_at->format('Y-m-d') : $result->created_at->format('Y-m-d'),
            'created_at' => $result->submitted_at ?? $result->created_at,
        ]);
    }
    
    // ✅ ترتيب النتائج من الأحدث إلى الأقدم
    $sortedResults = $results->sortByDesc('created_at')->values();
    
    // ✅ عدد النتائج حسب الـ limit
    $latestResults = $sortedResults->take($limit);
    
    // ✅ إحصائيات سريعة
    $totalResults = $sortedResults->count();
    $avgPercentage = $totalResults > 0 ? round($sortedResults->avg('percentage')) : 0;
    $bestResult = $totalResults > 0 ? $sortedResults->max('percentage') : 0;
    $worstResult = $totalResults > 0 ? $sortedResults->min('percentage') : 0;
    
    // ✅ توزيع النتائج حسب النوع
    $examCount = $examResults->count();
    $assignmentCount = $assignmentResults->count();
    
    return response()->json([
        'success' => true,
        'data' => [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'phone' => $student->phone,
                'image_url' => $student->image_url,
            ],
            'stats' => [
                'total_results' => $totalResults,
                'average_percentage' => $avgPercentage,
                'best_result' => $bestResult,
                'worst_result' => $worstResult,
                'exam_count' => $examCount,
                'assignment_count' => $assignmentCount,
            ],
            'results' => $latestResults->map(function($result) {
                return [
                    'id' => $result['id'],
                    'type' => $result['type'],
                    'type_label' => $result['type_label'],
                    'title' => $result['title'],
                    'score' => $result['score'],
                    'total' => $result['total'],
                    'percentage' => $result['percentage'],
                    'date' => $result['date'],
                ];
            }),
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}



}
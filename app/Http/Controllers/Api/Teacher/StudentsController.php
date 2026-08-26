<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Subject;
use App\Models\Grade;
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


public function stats(Request $request)
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
    
    // جلب اشتراكات الطلاب في المواد المحددة فقط
    $subscriptions = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIds)
        ->with(['student', 'teacherSubjectGrade.subject', 'teacherSubjectGrade.grade'])
        ->get();
    
    // ✅ الإحصائيات دلوقتي على الطلاب في المواد المحددة فقط
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
            ]
        ]
    ], 200, [], JSON_UNESCAPED_UNICODE);
}
    
    /**
     * جلب قائمة الطلاب مع تفاصيلهم
     */
    public function students(Request $request)
    {
        $teacher = auth()->user();
        
        // جلب المواد اللي المدرس بيدرسها
        $teacherSubjects = TeacherSubjectGrade::where('teacher_id', $teacher->id)
            ->with(['subject', 'grade'])
            ->get();
        
        $subjectIds = $teacherSubjects->pluck('id');
        
        // فلترة حسب المادة والصف
        $subjectId = $request->subject_id;
        $gradeId = $request->grade_id;
        
        $query = StudentSubscription::whereIn('teacher_subject_grade_id', $subjectIds)
            ->with(['student', 'teacherSubjectGrade.subject', 'teacherSubjectGrade.grade']);
        
        // فلترة حسب المادة
        if ($subjectId && $subjectId !== 'all') {
            $query->whereHas('teacherSubjectGrade', function($q) use ($subjectId) {
                $q->where('subject_id', $subjectId);
            });
        }
        
        // فلترة حسب الصف
        if ($gradeId && $gradeId !== 'all') {
            $query->whereHas('teacherSubjectGrade', function($q) use ($gradeId) {
                $q->where('grade_id', $gradeId);
            });
        }
        
        // بحث
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('student', function($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                  ->orWhere('phone', 'LIKE', '%' . $search . '%');
            });
        }
        
        $subscriptions = $query->get();
        
        // تجميع الطلاب (مع تفاصيل كل طالب)
        $students = $subscriptions->groupBy('student_id')->map(function($subs) use ($teacher) {
            $student = $subs->first()->student;
            
            // جلب كل المواد اللي الطالب مشترك فيها مع هذا المدرس
            $studentSubjects = $subs->map(function($sub) {
                return [
                    'subject' => $sub->teacherSubjectGrade->subject->name,
                    'grade' => $sub->teacherSubjectGrade->grade->name,
                    'access_code' => $sub->teacherSubjectGrade->access_code,
                    'status' => $sub->status,
                    'is_banned' => $sub->is_banned,
                    'subscribed_at' => $sub->subscribed_at,
                    'expires_at' => $sub->expires_at,
                ];
            });
            
            // حساب نسبة التقدم (بناءً على الفيديوهات)
            $videos = Video::where('teacher_id', $teacher->id)
                ->where('status', 'approved')
                ->get();
            
            $totalVideos = $videos->count();
            $watchedVideos = VideoProgress::where('user_id', $student->id)
                ->whereIn('video_id', $videos->pluck('id'))
                ->where('is_completed', true)
                ->count();
            
            $progressPercentage = $totalVideos > 0 ? round(($watchedVideos / $totalVideos) * 100) : 0;
            
            // حالة الطالب
            $isActive = $student->is_active;
            $hasActiveSubscription = $subs->contains(function($sub) {
                return $sub->status === 'active' && $sub->is_banned == false;
            });
            
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
                'progress_percentage' => $progressPercentage,
                'watched_videos' => $watchedVideos,
                'total_videos' => $totalVideos,
                'completed_content' => "{$watchedVideos} / {$totalVideos}",
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
                'current_page' => $currentPage,
                'data' => $paginated,
                'total' => $total,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage),
            ],
            'filters' => [
                'subject_id' => $request->subject_id,
                'grade_id' => $request->grade_id,
                'search' => $request->search,
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
        
        // جلب تقدم الطالب في الفيديوهات
        $videos = Video::where('teacher_id', $teacher->id)
            ->where('status', 'approved')
            ->get();
        
        $videoProgress = VideoProgress::where('user_id', $studentId)
            ->whereIn('video_id', $videos->pluck('id'))
            ->get();
        
        $totalVideos = $videos->count();
        $watchedVideos = $videoProgress->where('is_completed', true)->count();
        $progressPercentage = $totalVideos > 0 ? round(($watchedVideos / $totalVideos) * 100) : 0;
        
        // جلب نتائج الامتحانات
        $examResults = ExamResult::where('student_id', $studentId)
            ->with(['exam'])
            ->get();
        
        // جلب تسليمات الواجبات
        $assignmentSubmissions = AssignmentSubmission::where('student_id', $studentId)
            ->with(['assignment'])
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'student' => [
                    'id' => $student->id,
                    'name' => $student->name,
                    'phone' => $student->phone,
                    'email' => $student->email,
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
                'progress' => [
                    'total_videos' => $totalVideos,
                    'watched_videos' => $watchedVideos,
                    'progress_percentage' => $progressPercentage,
                    'completed_content' => "{$watchedVideos} / {$totalVideos}",
                    'video_progress' => $videoProgress->map(function($vp) {
                        return [
                            'video_id' => $vp->video_id,
                            'video_title' => $vp->video->title ?? '',
                            'progress_percentage' => $vp->progress_percentage,
                            'is_completed' => $vp->is_completed,
                        ];
                    }),
                ],
                'exam_results' => $examResults->map(function($er) {
                    return [
                        'exam_id' => $er->exam_id,
                        'exam_title' => $er->exam->title ?? '',
                        'score' => $er->score,
                        'total' => $er->total,
                        'percentage' => $er->percentage,
                        'status' => $er->status,
                        'completed_at' => $er->completed_at ? $er->completed_at->format('Y-m-d H:i') : null,
                    ];
                }),
                'assignment_submissions' => $assignmentSubmissions->map(function($as) {
                    return [
                        'assignment_id' => $as->assignment_id,
                        'assignment_title' => $as->assignment->title ?? '',
                        'submitted_at' => $as->submitted_at ? $as->submitted_at->format('Y-m-d H:i') : null,
                        'grade' => $as->grade,
                        'feedback' => $as->feedback,
                        'status' => $as->status,
                    ];
                }),
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
}
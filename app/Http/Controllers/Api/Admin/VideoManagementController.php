<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Video;
use App\Models\User;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\TeacherSubjectGrade;
use App\Models\Notification;
use App\Events\NewNotificationEvent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class VideoManagementController extends Controller
{
     public function filterOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => User::where('role', 'teacher')
                    ->where('is_active', true)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                
                'subjects' => Subject::where('is_active', true)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                
                'grades' => Grade::select('id', 'name')
                    ->orderBy('name')
                    ->get(),
                'statuses' => [
                ['value' => 'all', 'label' => 'كل الحالات'],
                ['value' => 'pending', 'label' => 'بانتظار المراجعة'],
                ['value' => 'approved', 'label' => 'تمت الموافقة'],
                ['value' => 'rejected', 'label' => 'مرفوض'],
            ],
            ]
        ]);
    }
    /**
     * عرض كل الفيديوهات مع فلترة وبحث وإحصائيات
     */
     public function index(Request $request)
    {
        // =============================================
        // 1. بناء الاستعلام الأساسي
        // =============================================
        $query = Video::with([
            'teacher:id,name',
            'subject:id,name',
            'teacherSubjectGrade.grade:id,name'
        ]);

        // =============================================
        // 2. الفلترة حسب الحالة
        // =============================================
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // =============================================
        // 3. الفلترة حسب المدرس
        // =============================================
        if ($request->has('teacher_id') && $request->teacher_id) {
            $query->where('teacher_id', $request->teacher_id);
        }

        // =============================================
        // 4. الفلترة حسب المادة
        // =============================================
        if ($request->has('subject_id') && $request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        // =============================================
        // 5. الفلترة حسب الصف
        // =============================================
        if ($request->has('grade_id') && $request->grade_id) {
            $query->whereHas('teacherSubjectGrade', function ($q) use ($request) {
                $q->where('grade_id', $request->grade_id);
            });
        }

        // =============================================
        // 8. الترتيب
        // =============================================
        $sortField = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        // =============================================
        // 9. التصفح (Pagination) مع per_page
        // =============================================
        $perPage = $request->per_page ?? 15;
        $videos = $query->paginate($perPage);

        // =============================================
        // 10. تنسيق البيانات (العنوان, المدرس, التاريخ, الصف, المادة فقط)
        // =============================================
        $formattedVideos = $videos->through(function ($video) {
            return [
                'id' => $video->id,
                'video_url'=>$video->video_path,
                'title' => $video->title,
                'teacher_name' => $video->teacher->name ?? null,
                'subject_name' => $video->subject->name ?? null,
                'grade_name' => $video->teacherSubjectGrade->grade->name ?? null,
                'status' => $video->status,
                'created_at' => $video->created_at->format('Y-m-d H:i:s'),
                'duration_formatted' => $video->duration ? $this->formatDuration($video->duration) : null,
            ];
        });

        // =============================================
        // 11. إحصائيات الفيديوهات (حسب الطلب)
        // =============================================
        $stats = $this->getStats();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'data' => $formattedVideos,
            'pagination' => [
                'current_page' => $videos->currentPage(),
                'per_page' => $videos->perPage(),
                'total' => $videos->total(),
                'last_page' => $videos->lastPage(),
                'next_page_url' => $videos->nextPageUrl(),
                'prev_page_url' => $videos->previousPageUrl(),
            ]
        ]);
    }

    /**
     * جلب إحصائيات الفيديوهات
     */
    private function getStats()
    {
        $currentMonth = now()->month;
        $currentYear = now()->year;

        return [
            // كل الفيديوهات المرفوعة (كل الأوقات)
            'total_videos' => Video::count(),
            
            // المرفوض هذا الشهر
            'rejected_this_month' => Video::where('status', 'rejected')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->count(),
            
            // الموافق عليها هذا الشهر
            'approved_this_month' => Video::where('status', 'approved')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->count(),
            
            // بانتظار المراجعة هذا الشهر
            'pending_this_month' => Video::where('status', 'pending')
                ->whereMonth('created_at', $currentMonth)
                ->whereYear('created_at', $currentYear)
                ->count(),
            
            // كل الموافق عليها (كل الأوقات)
            'total_approved' => Video::where('status', 'approved')->count(),
            
            // كل المرفوضة (كل الأوقات)
            'total_rejected' => Video::where('status', 'rejected')->count(),
            
            // كل البانتظار (كل الأوقات)
            'total_pending' => Video::where('status', 'pending')->count(),
        ];
    }


public function show($id)
{
    $video = Video::with([
        'teacher:id,name,image',
        'subject:id,name',
        'teacherSubjectGrade.grade:id,name',
        'reviewer:id,name'
    ])->find($id);

    if (!$video) {
        return response()->json([
            'success' => false,
            'message' => 'الفيديو غير موجود'
        ], 404);
    }

    // تجهيز رابط الفيديو للتشغيل
    $videoUrl = null;
    if ($video->video_path) {
        $videoUrl = asset('storage/' . $video->video_path);
    }

    // تجهيز الصورة المصغرة
    $thumbnailUrl = null;
    if ($video->thumbnail) {
        $thumbnailUrl = asset('storage/' . $video->thumbnail);
    }

    // ✅ جلب تقدم المشاهدة للأدمن الحالي
    $progress = \App\Models\VideoProgress::where('video_id', $id)
        ->where('user_id', auth()->id())
        ->first();

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'teacher' => [
                'id' => $video->teacher->id ?? null,
                'name' => $video->teacher->name ?? null,
                'image' => $video->teacher->image ? asset('storage/' . $video->teacher->image) : null,
            ],
            'subject' => [
                'id' => $video->subject->id ?? null,
                'name' => $video->subject->name ?? null,
            ],
            'grade' => [
                'id' => $video->teacherSubjectGrade->grade->id ?? null,
                'name' => $video->teacherSubjectGrade->grade->name ?? null,
            ],
            'duration' => $video->duration,
            'duration_formatted' => $video->duration ? $this->formatDuration($video->duration) : null,
            'video_url' => $videoUrl,
            'thumbnail' => $thumbnailUrl,
            'status' => $video->status,
            'status_label' => $this->getStatusLabel($video->status),
            'rejection_reason' => $video->rejection_reason,
            'created_at' => $video->created_at->format('Y-m-d H:i:s'),
            'views_count' => $video->views_count,
            'reviewer' => $video->reviewer ? [
                'id' => $video->reviewer->id,
                'name' => $video->reviewer->name,
            ] : null,
            'reviewed_at' => $video->reviewed_at ? $video->reviewed_at->format('Y-m-d H:i:s') : null,
            // ✅ تقدم المشاهدة
            'progress' => $progress ? [
                'last_position' => $progress->last_position,
                'progress_percentage' => $progress->progress_percentage,
                'is_completed' => $progress->is_completed,
                'last_watched_at' => $progress->last_watched_at,
            ] : [
                'last_position' => 0,
                'progress_percentage' => 0,
                'is_completed' => false,
                'last_watched_at' => null,
            ]
        ]
    ]);
}


    /**
     * قبول الفيديو ونشره
     */
    public function approve($id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => 'الفيديو غير موجود'
            ], 404);
        }

        // التأكد من أن الفيديو في انتظار المراجعة
        if ($video->status !== 'pending' && $video->status !== 'revision') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن قبول هذا الفيديو لأنه ليس في حالة مراجعة'
            ], 422);
        }

        $video->update([
            'status' => 'approved',
            'is_published' => true,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        // إرسال إشعار للمدرس
        $this->notifyTeacher($video, 'approved');

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الفيديو ونشره للطلاب',
            'data' => $video->load(['teacher', 'subject', 'reviewer'])
        ]);
    }

    /**
     * رفض الفيديو
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|min:10|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $video = Video::find($id);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => 'الفيديو غير موجود'
            ], 404);
        }

        if ($video->status !== 'pending' && $video->status !== 'revision') {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن رفض هذا الفيديو'
            ], 422);
        }

        $video->update([
            'status' => 'rejected',
            'is_published' => false,
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        // إرسال إشعار للمدرس
        $this->notifyTeacher($video, 'rejected');

        return response()->json([
            'success' => true,
            'message' => 'تم رفض الفيديو',
            'data' => $video->load(['teacher', 'subject', 'reviewer'])
        ]);
    }


public function restoreToPending($id)
{
    $video = Video::find($id);

    if (!$video) {
        return response()->json([
            'success' => false,
            'message' => 'الفيديو غير موجود'
        ], 404);
    }

    // التأكد من أن الفيديو مرفوض
    if ($video->status !== 'rejected') {
        return response()->json([
            'success' => false,
            'message' => 'يمكن فقط إعادة الفيديوهات المرفوضة للمراجعة'
        ], 422);
    }

    $video->update([
        'status' => 'pending',
        'is_published' => false,
        'rejection_reason' => null, // مسح سبب الرفض
        'reviewed_by' => null,
        'reviewed_at' => null,
    ]);

    //  إرسال إشعار للمدرس
    $this->notifyTeacherRestored($video);

    return response()->json([
        'success' => true,
        'message' => 'تم إعادة الفيديو للمراجعة',
        'data' => $video->load(['teacher', 'subject'])
    ]);
}

/**
 * إرسال إشعار للمدرس عند إعادة الفيديو للمراجعة
 */
private function notifyTeacherRestored($video)
{
    $notification = Notification::create([
        'user_id' => $video->teacher_id,
        'triggered_by_id' => auth()->id(),
        'type' => 'video_restored',
        'message' => "تم إعادة فيديو: {$video->title} للمراجعة",
        'data' => [
            'video_id' => $video->id,
            'video_title' => $video->title,
            'action' => 'restored',
            'reviewer_name' => auth()->user()->name,
            'reviewed_at' => now()->format('Y-m-d H:i:s'),
        ],
        'is_read' => false,
    ]);

    try {
        broadcast(new NewNotificationEvent($notification));
    } catch (\Exception $e) {
        // لو الـ broadcasting مش شغال
    }
}

    /**
     * حذف فيديو (للأدمن)
     */
    public function destroy($id)
    {
        $video = Video::find($id);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => 'الفيديو غير موجود'
            ], 404);
        }

        // حذف الملف من التخزين
        if ($video->video_path) {
            \Storage::disk('public')->delete($video->video_path);
        }

        // حذف الصورة المصغرة
        if ($video->thumbnail && !str_contains($video->thumbnail, 'youtube')) {
            \Storage::disk('public')->delete($video->thumbnail);
        }

        $video->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الفيديو بنجاح'
        ]);
    }

    
 
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
        return sprintf('%02d:%02d', $minutes, $secs);
    }

    /**
     * إرسال إشعار للمدرس
     */
    private function notifyTeacher($video, string $action)
    {
        $messages = [
            'approved' => " تم قبول فيديو: {$video->title} ونشره للطلاب",
            'rejected' => " تم رفض فيديو: {$video->title}",
            'revision' => " مطلوب تعديل على فيديو: {$video->title}",
        ];

        $types = [
            'approved' => 'video_approved',
            'rejected' => 'video_rejected',
            'revision' => 'video_revision',
        ];

        $notification = Notification::create([
            'user_id' => $video->teacher_id,
            'triggered_by_id' => auth()->id(),
            'type' => $types[$action],
            'message' => $messages[$action],
            'data' => [
                'video_id' => $video->id,
                'video_title' => $video->title,
                'action' => $action,
                'rejection_reason' => $video->rejection_reason,
                'reviewer_name' => auth()->user()->name,
                'reviewed_at' => now(),
            ],
            'is_read' => false,
        ]);

        // بث الإشعار
        try {
            broadcast(new NewNotificationEvent($notification));
        } catch (\Exception $e) {
            // لو الـ broadcasting مش شغال
        }
    }
    private function getStatusLabel(string $status): string
{
    $labels = [
        'pending' => 'بانتظار المراجعة',
        'approved' => 'مقبولة',
        'rejected' => 'مرفوضة',
        'revision' => 'مطلوب تعديل',
    ];

    return $labels[$status] ?? $status;
}
}


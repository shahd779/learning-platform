<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Events\NewNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\TeacherSubjectGrade;
use App\Models\User;
use App\Models\Video;
use FFMpeg\FFProbe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use getID3;

class CourseContentController extends Controller
{
    /**
     * جلب المواد والصفوف المتاحة للمدرس (للدروب داون)
     */
    public function getTeacherSubjects()
    {
        $teacherId = auth()->id();

        // جلب المواد اللي المدرس بيدرسها (بدون تكرار)
        $subjects = TeacherSubjectGrade::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->with('subject')
            ->get()
            ->unique('subject_id')
            ->map(function ($item) {
                return [
                    'id' => $item->subject_id,
                    'name' => $item->subject->name,
                ];
            })
            ->values();

        // جلب الصفوف اللي المدرس بيدرس فيها (بدون تكرار)
        $grades = TeacherSubjectGrade::where('teacher_id', $teacherId)
            ->where('is_active', true)
            ->with('grade')
            ->get()
            ->unique('grade_id')
            ->map(function ($item) {
                return [
                    'id' => $item->grade_id,
                    'name' => $item->grade->name,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'subjects' => $subjects,
                'grades' => $grades,
            ]
        ]);
    }

    /**
     * جلب الـ teacher_subject_grade_id بناءً على المادة والصف المختارين
     */
    public function getSubjectGradeId(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|exists:subjects,id',
            'grade_id' => 'required|exists:grades,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $teacherId = auth()->id();

        $teacherSubjectGrade = TeacherSubjectGrade::where('teacher_id', $teacherId)
            ->where('subject_id', $request->subject_id)
            ->where('grade_id', $request->grade_id)
            ->where('is_active', true)
            ->first();

        if (!$teacherSubjectGrade) {
            return response()->json([
                'success' => false,
                'message' => 'هذه المادة غير متاحة لك في هذا الصف'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'teacher_subject_grade_id' => $teacherSubjectGrade->id,
                'access_code' => $teacherSubjectGrade->access_code,
            ]
        ]);
    }

/**
 * رفع جزء من الفيديو (Chunk Upload)
 */
public function uploadChunk(Request $request)
{
    $validator = Validator::make($request->all(), [
        'chunk' => 'required|file|mimes:mp4,avi,mov,mkv,wmv,flv,webm,bin|max:5120',
        'chunk_index' => 'required|integer|min:0',
        'total_chunks' => 'required|integer|min:1',
        'upload_id' => 'required|string|max:255',
        'teacher_subject_grade_id' => 'required|exists:teacher_subject_grade,id',
        'title' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        'order' => 'nullable|integer|min:0',
    ], [
        'chunk.required' => 'جزء الفيديو مطلوب',
        'chunk.mimes' => 'صيغة الفيديو غير مدعومة',
        'chunk.max' => 'حجم الجزء لا يتجاوز 5 ميجابايت',
        'upload_id.required' => 'معرف الرفع مطلوب',
        'title.required' => 'عنوان الفيديو مطلوب',
        'teacher_subject_grade_id.required' => 'يجب اختيار المادة والصف',
        'thumbnail.image' => 'يجب أن تكون الصورة المصغرة من نوع صورة',
        'thumbnail.mimes' => 'الصيغ المدعومة للصورة: jpeg, png, jpg, gif',
        'thumbnail.max' => 'حجم الصورة لا يتجاوز 5 ميجابايت',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    // التأكد من أن المدرس يملك هذه المادة
    $teacherSubjectGrade = TeacherSubjectGrade::where('id', $request->teacher_subject_grade_id)
        ->where('teacher_id', auth()->id())
        ->where('is_active', true)
        ->first();

    if (!$teacherSubjectGrade) {
        return response()->json([
            'success' => false,
            'message' => 'هذه المادة غير متاحة لك'
        ], 403);
    }

    // إنشاء مجلد مؤقت للـ Upload
    $tempDir = storage_path('app/public/uploads/temp/' . $request->upload_id);
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0777, true);
    }

    // حفظ الجزء
    $chunkFile = $request->file('chunk');
    $chunkPath = $tempDir . '/' . $request->chunk_index . '.part';
    $chunkFile->move($tempDir, $request->chunk_index . '.part');

    // التحقق من اكتمال كل الأجزاء
    $totalChunks = $request->total_chunks;
    $uploadedChunks = glob($tempDir . '/*.part');
    $uploadedCount = count($uploadedChunks);

    if ($uploadedCount == $totalChunks) {
        // ✅ كل الأجزاء رفعت → جمعهم في ملف واحد مع الإعدادات
        $video = $this->assembleChunks($request->upload_id, $totalChunks, $teacherSubjectGrade, $request);

        return response()->json([
            'success' => true,
            'message' => 'تم رفع الفيديو بنجاح',
            'data' => [
                'video' => [
                    'id' => $video->id,
                    'title' => $video->title,
                    'description' => $video->description,
                    'video_path' => $video->video_path ? asset('storage/' . $video->video_path) : null,
                    'thumbnail' => $video->thumbnail ? asset('storage/' . $video->thumbnail) : null,
                    'duration' => $video->duration,
                    'duration_formatted' => $video->duration ? $this->formatDuration($video->duration) : null,
                    'status' => $video->status,
                    'status_label' => 'بانتظار المراجعة',
                    'is_active' => $video->is_active,
                    'is_available' => $video->is_available,
                    'available_until' => $video->available_until,
                    'max_watch_count' => $video->max_watch_count,
                    'created_at' => $video->created_at->format('Y-m-d H:i:s'),
                ],
                'upload_id' => $request->upload_id,
                'progress' => 100,
            ]
        ]);
    }

    // لسه في أجزاء متبقية
    $progress = round(($uploadedCount / $totalChunks) * 100);

    return response()->json([
        'success' => true,
        'message' => 'تم رفع الجزء بنجاح',
        'data' => [
            'upload_id' => $request->upload_id,
            'chunk_index' => $request->chunk_index,
            'progress' => $progress,
            'uploaded' => $uploadedCount,
            'total' => $totalChunks,
        ]
    ]);
}

/**
 * جمع الأجزاء في ملف واحد مع الصورة المصغرة والإعدادات
 */
private function assembleChunks($uploadId, $totalChunks, $teacherSubjectGrade, $request)
{
    $tempDir = storage_path('app/public/uploads/temp/' . $uploadId);
    $finalFileName = 'video_' . time() . '_' . uniqid() . '.mp4';
    $finalPath = storage_path('app/public/videos/' . $finalFileName);

    // التأكد من وجود المجلد
    if (!file_exists(storage_path('app/public/videos'))) {
        mkdir(storage_path('app/public/videos'), 0777, true);
    }

    // فتح الملف النهائي للكتابة
    $finalFile = fopen($finalPath, 'wb');

    // دمج الأجزاء بالترتيب
    for ($i = 0; $i < $totalChunks; $i++) {
        $chunkPath = $tempDir . '/' . $i . '.part';
        if (file_exists($chunkPath)) {
            $chunkData = file_get_contents($chunkPath);
            fwrite($finalFile, $chunkData);
            unlink($chunkPath);
        }
    }

    fclose($finalFile);

    // حذف المجلد المؤقت
    rmdir($tempDir);

    // استخراج مدة الفيديو
    $duration = $this->getVideoDuration($finalPath);

    // =============================================
    // معالجة الصورة المصغرة (thumbnail)
    // =============================================
    $thumbnailPath = null;
    if ($request->hasFile('thumbnail')) {
        $thumbnailFile = $request->file('thumbnail');
        $thumbnailName = 'thumb_' . time() . '_' . uniqid() . '.' . $thumbnailFile->getClientOriginalExtension();
        $thumbnailPath = $thumbnailFile->storeAs('thumbnails', $thumbnailName, 'public');
    }

    // =============================================
    // ✅ جلب الإعدادات العامة للمدرس
    // =============================================
    $settings = auth()->user()->teacherSettings;
    if (!$settings) {
        $settings = auth()->user()->createDefaultSettings();
    }
    $defaultVideoSettings = $settings->getDefaultVideoSettings();

    // =============================================
    // ✅ حفظ بيانات الفيديو في قاعدة البيانات مع الإعدادات
    // =============================================
    $video = Video::create([
        'teacher_subject_grade_id' => $teacherSubjectGrade->id,
        'teacher_id' => auth()->id(),
        'subject_id' => $teacherSubjectGrade->subject_id,
        'title' => $request->title,
        'description' => $request->description,
        'order' => $request->order ?? 0,
        'video_path' => 'videos/' . $finalFileName,
        'thumbnail' => $thumbnailPath,
        'duration' => $duration,
        'status' => 'pending',
        'is_published' => false,
        // ✅ إعدادات الفيديو من الإعدادات العامة للمدرس
        'is_active' => $defaultVideoSettings['is_active'],
        'is_available' => $defaultVideoSettings['is_available'],
        'available_until' => $defaultVideoSettings['available_until'],
        'max_watch_count' => $defaultVideoSettings['max_watch_count'],
    ]);

    // إرسال إشعار للأدمنة
    $this->notifyAdmins($video);

    return $video;
}

    /**
     * إلغاء رفع الفيديو وحذف الأجزاء المؤقتة
     */
    public function cancelUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tempDir = storage_path('app/public/uploads/temp/' . $request->upload_id);

        if (file_exists($tempDir)) {
            // حذف كل الأجزاء
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($tempDir);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء رفع الفيديو',
        ]);
    }

    /**
     * التحقق من حالة الرفع
     */
    public function checkUploadStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'upload_id' => 'required|string|max:255',
            'total_chunks' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $tempDir = storage_path('app/public/uploads/temp/' . $request->upload_id);

        if (!file_exists($tempDir)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'uploaded_chunks' => 0,
                    'total_chunks' => $request->total_chunks,
                    'progress' => 0,
                    'status' => 'not_started',
                ]
            ]);
        }

        $uploadedChunks = glob($tempDir . '/*.part');
        $uploadedCount = count($uploadedChunks);
        $progress = round(($uploadedCount / $request->total_chunks) * 100);

        return response()->json([
            'success' => true,
            'data' => [
                'uploaded_chunks' => $uploadedCount,
                'total_chunks' => $request->total_chunks,
                'progress' => $progress,
                'status' => $progress == 100 ? 'completed' : 'in_progress',
            ]
        ]);
    }

    /**
     * إرسال إشعارات للأدمنة عند رفع فيديو جديد
     */
    private function notifyAdmins($video): void
    {
        $admins = User::where('role', 'admin')
            ->where('is_active', true)
            ->get();

        foreach ($admins as $admin) {
            $notification = Notification::create([
                'user_id' => $admin->id,
                'triggered_by_id' => auth()->id(),
                'type' => 'video_uploaded',
                'message' => "📹 قام المدرس {$video->teacher->name} برفع فيديو جديد: {$video->title}",
                'data' => [
                    'video_id' => $video->id,
                    'video_title' => $video->title,
                    'teacher_id' => $video->teacher_id,
                    'teacher_name' => $video->teacher->name,
                    'subject_name' => $video->subject->name,
                    'subject_id' => $video->subject_id,
                    'status' => 'pending',
                    'created_at' => $video->created_at,
                ],
                'is_read' => false,
            ]);

            try {
                broadcast(new NewNotificationEvent($notification));
            } catch (\Exception $e) {
                // لو الـ broadcasting مش شغال
            }
        }
    }

    /**
     * استخراج مدة الفيديو من الملف
     */
    private function getVideoDuration($filePath)
    {
        try {
            // استخدام FFmpeg
            $ffprobe = FFProbe::create();
            $duration = $ffprobe->streams($filePath)
                ->videos()
                ->first()
                ->get('duration');

            return (int) ceil($duration);
        } catch (\Exception $e) {
            // استخدام getID3 كبديل
            try {
                if (!class_exists(getID3::class)) {
                    return null;
                }

                $getID3 = new getID3();
                $file = $getID3->analyze($filePath);
                return isset($file['playtime_seconds']) ? (int) ceil($file['playtime_seconds']) : null;
            } catch (\Exception $e) {
                return null;
            }
        }
    }

    /**
     * تنسيق المدة (ثواني → ساعة:دقيقة:ثانية)
     */
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
     * توليد اسم فريد للملف
     */
    private function generateUniqueFileName($file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y_m_d_H_i_s');
        $random = Str::random(8);
        $cleanName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        
        return "{$cleanName}_{$timestamp}_{$random}.{$extension}";
    }
}
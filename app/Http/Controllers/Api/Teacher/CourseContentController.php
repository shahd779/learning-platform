<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Events\NewNotificationEvent;
use App\Http\Controllers\Controller;
use App\Models\File;
use App\Models\Notification;
use App\Models\TeacherSubjectGrade;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoProgress;
use App\Traits\VideoHelperTrait;
use FFMpeg\FFProbe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use getID3;

class CourseContentController extends Controller
{
    use VideoHelperTrait;

    /**
     * جلب المواد والصفوف المتاحة للمدرس (للدروب داون)
     */
    public function getTeacherSubjects()
    {
        $teacherId = auth()->id();

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

        $tempDir = storage_path('app/public/uploads/temp/' . $request->upload_id);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $chunkFile = $request->file('chunk');
        $chunkPath = $tempDir . '/' . $request->chunk_index . '.part';
        $chunkFile->move($tempDir, $request->chunk_index . '.part');

        $totalChunks = $request->total_chunks;
        $uploadedChunks = glob($tempDir . '/*.part');
        $uploadedCount = count($uploadedChunks);

        if ($uploadedCount == $totalChunks) {
            $video = $this->assembleChunks($request->upload_id, $totalChunks, $teacherSubjectGrade, $request);

            return response()->json([
                'success' => true,
                'message' => 'تم رفع الفيديو بنجاح',
                'data' => [
                    'video' => $this->formatVideoData($video),
                    'upload_id' => $request->upload_id,
                    'progress' => 100,
                ]
            ]);
        }

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
     * جمع الأجزاء في ملف واحد
     */
    private function assembleChunks($uploadId, $totalChunks, $teacherSubjectGrade, $request)
    {
        $tempDir = storage_path('app/public/uploads/temp/' . $uploadId);
        $finalFileName = 'video_' . time() . '_' . uniqid() . '.mp4';
        $finalPath = storage_path('app/public/videos/' . $finalFileName);

        if (!file_exists(storage_path('app/public/videos'))) {
            mkdir(storage_path('app/public/videos'), 0777, true);
        }

        $finalFile = fopen($finalPath, 'wb');

        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkPath = $tempDir . '/' . $i . '.part';
            if (file_exists($chunkPath)) {
                $chunkData = file_get_contents($chunkPath);
                fwrite($finalFile, $chunkData);
                unlink($chunkPath);
            }
        }

        fclose($finalFile);
        rmdir($tempDir);

        $duration = $this->getVideoDuration($finalPath);

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailFile = $request->file('thumbnail');
            $thumbnailName = 'thumb_' . time() . '_' . uniqid() . '.' . $thumbnailFile->getClientOriginalExtension();
            $thumbnailPath = $thumbnailFile->storeAs('thumbnails', $thumbnailName, 'public');
        }

        $settings = auth()->user()->teacherSettings;
        if (!$settings) {
            $settings = auth()->user()->createDefaultSettings();
        }
        $defaultVideoSettings = $settings->getDefaultVideoSettings();

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
            'is_active' => $defaultVideoSettings['is_active'],
            'is_available' => $defaultVideoSettings['is_available'],
            'available_until' => $defaultVideoSettings['available_until'],
            'max_watch_count' => $defaultVideoSettings['max_watch_count'],
        ]);

        $this->notifyAdmins($video);

        return $video;
    }

    /**
     * إلغاء رفع الفيديو
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
     * جلب جميع الفيديوهات المتوافق عليها
     */
    public function getApprovedVideos(Request $request)
    {
        $teacherId = auth()->id();

        $query = Video::where('teacher_id', $teacherId)
            ->where('status', 'approved')
            ->where('is_published', true)
            ->with(['subject', 'teacherSubjectGrade.grade']);

        if ($request->has('subject_id') && $request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->has('grade_id') && $request->grade_id) {
            $query->whereHas('teacherSubjectGrade', function ($q) use ($request) {
                $q->where('grade_id', $request->grade_id);
            });
        }

        if ($request->has('status_filter') && $request->status_filter) {
            switch ($request->status_filter) {
                case 'available':
                    $query->where('is_active', true)
                        ->where(function ($q) {
                            $q->whereNull('available_until')
                              ->orWhere('available_until', '>', now()->addDays(2));
                        });
                    break;

                case 'expiring_soon':
                    $query->where('is_active', true)
                        ->whereNotNull('available_until')
                        ->whereBetween('available_until', [now(), now()->addDays(2)]);
                    break;

                case 'teacher_only':
                    $query->where('is_active', true)
                        ->whereNotNull('available_until')
                        ->where('available_until', '<=', now());
                    break;

                case 'inactive':
                    $query->where('is_active', false);
                    break;
            }
        }

        if ($request->has('search') && $request->search) {
            $query->where('title', 'LIKE', '%' . $request->search . '%');
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->per_page ?? 15;
        $videos = $query->paginate($perPage);

        $formattedVideos = $videos->through(function ($video) {
            return $this->formatVideoData($video);
        });

        $filters = [
            'statuses' => [
                ['value' => 'available', 'label' => 'متاح'],
                ['value' => 'expiring_soon', 'label' => 'سينتهي خلال يومين'],
                ['value' => 'teacher_only', 'label' => 'متاح للمدرس فقط'],
                ['value' => 'inactive', 'label' => 'غير مفعل'],
            ],
            'subjects' => $this->getFilterOptions()['subjects'],
            'grades' => $this->getFilterOptions()['grades'],
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedVideos,
            'filters' => $filters,
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
     * جلب الفيديوهات البيندنج
     */
    public function getPendingVideos(Request $request)
    {
        $teacherId = auth()->id();

        $query = Video::where('teacher_id', $teacherId)
            ->where('status', 'pending')
            ->with(['subject', 'teacherSubjectGrade.grade']);

        if ($request->has('subject_id') && $request->subject_id) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->has('grade_id') && $request->grade_id) {
            $query->whereHas('teacherSubjectGrade', function ($q) use ($request) {
                $q->where('grade_id', $request->grade_id);
            });
        }

        if ($request->has('search') && $request->search) {
            $query->where('title', 'LIKE', '%' . $request->search . '%');
        }

        $query->orderBy('created_at', 'asc');
        $videos = $query->get();

        $formattedVideos = $videos->map(function ($video) {
            return $this->formatVideoData($video);
        });

        return response()->json([
            'success' => true,
            'data' => $formattedVideos,
        ]);
    }

    /**
     * جلب محتوى المادة (فيديوهات + ملفات)
     */
    public function getSubjectContent(Request $request)
    {
        $teacherId = auth()->id();

        $subjectId = $request->subject_id;
        $gradeId = $request->grade_id;
        $videoSort = $request->video_sort ?? 'desc';
        $fileSort = $request->file_sort ?? 'desc';
        $limit = $request->limit ?? 3;

        $query = TeacherSubjectGrade::where('teacher_id', $teacherId);

        if ($subjectId) {
            $query->where('subject_id', $subjectId);
        }

        if ($gradeId) {
            $query->where('grade_id', $gradeId);
        }

        $teacherSubjectGrades = $query->pluck('id');

        if ($teacherSubjectGrades->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'total_videos' => 0,
                        'total_files' => 0,
                        'total_views' => 0,
                        'pending_videos' => 0,
                    ],
                    'videos' => [],
                    'files' => [],
                ]
            ]);
        }

        $stats = [
            'total_videos' => Video::whereIn('teacher_subject_grade_id', $teacherSubjectGrades)
                ->where('status', 'approved')
                ->count(),

            'total_files' => File::whereIn('teacher_subject_grade_id', $teacherSubjectGrades)->count(),

            'total_views' => Video::whereIn('teacher_subject_grade_id', $teacherSubjectGrades)
                ->where('status', 'approved')
                ->sum('views_count'),

            'pending_videos' => Video::whereIn('teacher_subject_grade_id', $teacherSubjectGrades)
                ->where('status', 'pending')
                ->count(),
        ];

        $videosQuery = Video::whereIn('teacher_subject_grade_id', $teacherSubjectGrades)
            ->where('status', 'approved')
            ->with(['subject', 'teacherSubjectGrade.grade']);

        $videosQuery->orderBy('created_at', $videoSort);
        $videos = $videosQuery->limit($limit)->get();

        $formattedVideos = $videos->map(function ($video) {
            return $this->formatVideoData($video);
        });

        $filesQuery = File::whereIn('teacher_subject_grade_id', $teacherSubjectGrades)
            ->with(['subject', 'teacherSubjectGrade.grade']);

        $filesQuery->orderBy('created_at', $fileSort);
        $files = $filesQuery->limit($limit)->get();

        $formattedFiles = $files->map(function ($file) {
            return $this->formatFileData($file);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'videos' => $formattedVideos,
                'files' => $formattedFiles,
            ]
        ]);
    }

    /**
     * تعديل اسم ووصف الفيديو
     */
    public function updateVideo(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $video = Video::where('teacher_id', auth()->id())
            ->where('status', 'approved')
            ->find($id);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => ' عذراً، هذا الفيديو غير موجود أو لم تتم الموافقة عليه بعد.'
            ], 404);
        }

        $data = [];
        if ($request->has('title')) {
            $data['title'] = $request->title;
        }
        if ($request->has('description')) {
            $data['description'] = $request->description;
        }

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => 'لا توجد بيانات للتحديث'
            ], 422);
        }

        $video->update($data);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الفيديو بنجاح',
            'data' => $this->formatVideoData($video),
        ]);
    }

    /**
     * تبديل حالة التفعيل لفيديو
     */
    public function toggleVideoActive($id)
    {
        $video = Video::where('teacher_id', auth()->id())
            ->where('status', 'approved')
            ->find($id);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => ' عذراً، هذا الفيديو غير موجود أو لم تتم الموافقة عليه بعد.'
            ], 404);
        }

        $video->is_active = !$video->is_active;
        $video->save();

        return response()->json([
            'success' => true,
            'message' => $video->is_active ? ' تم تفعيل الفيديو' : ' تم إلغاء تفعيل الفيديو',
            'data' => $this->formatVideoData($video),
        ]);
    }

    /**
     * تبديل تفعيل جميع الفيديوهات
     */
    public function toggleAllVideosActive()
    {
        $teacherId = auth()->id();

        $videos = Video::where('teacher_id', $teacherId)
            ->where('status', 'approved')
            ->get();

        if ($videos->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => ' لا توجد فيديوهات متوافق عليها.'
            ], 404);
        }

        $activeCount = $videos->where('is_active', true)->count();
        $totalCount = $videos->count();

        $newStatus = !($activeCount === $totalCount);

        Video::where('teacher_id', $teacherId)
            ->where('status', 'approved')
            ->update(['is_active' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => $newStatus
                ? " تم تفعيل جميع الفيديوهات ({$totalCount} فيديو)"
                : " تم إلغاء تفعيل جميع الفيديوهات ({$totalCount} فيديو)",
            'data' => [
                'total_videos' => $totalCount,
                'active_videos' => $newStatus ? $totalCount : 0,
                'inactive_videos' => $newStatus ? 0 : $totalCount,
                'new_status' => $newStatus ? 'active' : 'inactive',
            ]
        ]);
    }

    /**
     * حذف فيديو
     */
    public function deleteVideo($id)
    {
        $video = Video::where('teacher_id', auth()->id())
            ->where('status', 'approved')
            ->find($id);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' =>' عذراً، هذا الفيديو غير موجود أو لم تتم الموافقة عليه بعد.'
            ], 404);
        }

        if ($video->video_path) {
            Storage::disk('public')->delete($video->video_path);
        }
        if ($video->thumbnail) {
            Storage::disk('public')->delete($video->thumbnail);
        }

        VideoProgress::where('video_id', $video->id)->delete();
        $video->delete();

        return response()->json([
            'success' => true,
            'message' => ' تم حذف الفيديو بنجاح',
        ]);
    }

    /**
     * جلب خيارات إعدادات الفيديو
     */
    public function getVideoSettingsOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'max_watch_count' => [
                    ['value' => 'unlimited', 'label' => 'غير محدود'],
                    ['value' => 1, 'label' => 'مرة واحدة'],
                    ['value' => 2, 'label' => 'مرتين'],
                    ['value' => 3, 'label' => '3 مرات'],
                    ['value' => 5, 'label' => '5 مرات'],
                ],
                'availability' => [
                    ['value' => 'always', 'label' => 'اتاحة دائمة'],
                    ['value' => '1_day', 'label' => 'يوم واحد'],
                    ['value' => '2_days', 'label' => 'يومين'],
                    ['value' => '1_week', 'label' => 'أسبوع'],
                    ['value' => '1_month', 'label' => 'شهر'],
                ],
            ]
        ]);
    }

    /**
     * تحديث إعدادات فيديو معين
     */
    public function updateVideoSettings(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'max_watch_count' => 'nullable|in:unlimited,1,2,3,5',
            'availability' => 'nullable|in:always,1_day,2_days,1_week,1_month',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $video = Video::where('teacher_id', auth()->id())
            ->where('status', 'approved')
            ->find($id);

        if (!$video) {
            return response()->json([
                'success' => false,
                'message' => ' عذراً، هذا الفيديو غير موجود أو لم تتم الموافقة عليه بعد.'
            ], 404);
        }

        $data = [];

        if ($request->has('max_watch_count')) {
            $data['max_watch_count'] = $request->max_watch_count === 'unlimited'
                ? null
                : (int) $request->max_watch_count;
        }

        if ($request->has('availability')) {
            $availabilityMap = [
                'always' => ['is_available' => true, 'available_until' => null],
                '1_day' => ['is_available' => true, 'available_until' => now()->addDay()],
                '2_days' => ['is_available' => true, 'available_until' => now()->addDays(2)],
                '1_week' => ['is_available' => true, 'available_until' => now()->addWeek()],
                '1_month' => ['is_available' => true, 'available_until' => now()->addMonth()],
            ];

            if (isset($availabilityMap[$request->availability])) {
                $data['is_available'] = $availabilityMap[$request->availability]['is_available'];
                $data['available_until'] = $availabilityMap[$request->availability]['available_until'];
            }
        }

        if (empty($data)) {
            return response()->json([
                'success' => false,
                'message' => ' لم تقم بتحديد أي إعدادات.'
            ], 422);
        }

        $video->update($data);

        return response()->json([
            'success' => true,
            'message' => ' تم تحديث إعدادات الفيديو',
            'data' => [
                'id' => $video->id,
                'title' => $video->title,
                'max_watch_count' => $video->max_watch_count,
                'max_watch_count_label' => $video->max_watch_count ? $video->max_watch_count . ' مرات' : 'غير محدد',
                'is_available' => $video->is_available,
                'available_until' => $video->available_until ? $video->available_until->format('Y-m-d H:i:s') : null,
            ]
        ]);
    }

    /**
     * جلب خيارات حالة الفيديو
     */
    public function getVideoStatusOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => [
                    ['value' => 'available', 'label' => 'متاح'],
                    ['value' => 'expiring_soon', 'label' => 'سينتهي خلال يومين'],
                    ['value' => 'teacher_only', 'label' => 'متاح للمدرس فقط'],
                    ['value' => 'inactive', 'label' => 'غير مفعل'],
                ]
            ]
        ]);
    }

    /**
     * إرسال إشعار للأدمنة
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
     * استخراج مدة الفيديو
     */
    private function getVideoDuration($filePath)
    {
        try {
            $ffprobe = FFProbe::create();
            $duration = $ffprobe->streams($filePath)
                ->videos()
                ->first()
                ->get('duration');

            return (int) ceil($duration);
        } catch (\Exception $e) {
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
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
use Illuminate\Support\Facades\Http;
use getID3;

class CourseContentController extends Controller
{
       /**
     * جلب المواد والصفوف المتاحة للمدرس (للدروب داون)
     */
    public function getTeacherSubjects()
    {
        $teacherId = auth()->id();

        $subjects = TeacherSubjectGrade::where('teacher_id', $teacherId)
            ->with(['subject', 'grade'])
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subjects->map(function ($item) {
                return [
                    'id' => $item->id,
                    'teacher_subject_grade_id' => $item->id,
                    'subject_id' => $item->subject_id,
                    'subject_name' => $item->subject->name,
                    'grade_id' => $item->grade_id,
                    'grade_name' => $item->grade->name,
                    'access_code' => $item->access_code,
                ];
            })
        ]);
    }

    /**
     * رفع فيديو جديد (المدرس)
     */
    public function UploadVideo(Request $request)
{
    $validator = Validator::make($request->all(), [
        'teacher_subject_grade_id' => 'required|exists:teacher_subject_grade,id',
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
        'video_file' => 'required|file|mimes:mp4,avi,mov,mkv,wmv,flv,webm|max:512000',
        'order' => 'nullable|integer|min:0',
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
        ->first();

    if (!$teacherSubjectGrade) {
        return response()->json([
            'success' => false,
            'message' => 'هذه المادة غير متاحة لك'
        ], 403);
    }

    $data = [
        'teacher_subject_grade_id' => $request->teacher_subject_grade_id,
        'teacher_id' => auth()->id(),
        'subject_id' => $teacherSubjectGrade->subject_id,
        'title' => $request->title,
        'description' => $request->description,
        'order' => $request->order ?? 0,
        'status' => 'pending',
        'is_published' => false,
    ];

    // معالجة الفيديو المرفوع
    if ($request->hasFile('video_file')) {
        $file = $request->file('video_file');
        $path = $file->store('videos', 'public');
        $data['video_path'] = $path;

        // استخراج المدة تلقائياً
        $duration = $this->getVideoDuration($file->getPathname());
        if ($duration) {
            $data['duration'] = $duration;
        }
    }

    $video = Video::create($data);

    // ✅ إرسال إشعارات للأدمنة
    $this->notifyAdmins($video);

    return response()->json([
        'success' => true,
        'message' => 'تم رفع الفيديو بنجاح، في انتظار مراجعة الأدمن',
        'data' => $video
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

        // بث الإشعار فوراً عبر WebSocket (اختياري)
        try {
            broadcast(new NewNotificationEvent($notification));
        } catch (\Exception $e) {
            // لو الـ broadcasting مش شغال، الإشعارات بتتخزن في قاعدة البيانات على الأقل
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
     * استخراج مدة فيديو يوتيوب
      */
    // private function getYouTubeDuration($url)
    // {
    //     try {
    //         // استخراج الـ Video ID
    //         parse_str(parse_url($url, PHP_URL_QUERY), $params);
    //         $videoId = $params['v'] ?? null;

    //         if (!$videoId) {
    //             return null;
    //         }

    //         // استخدام YouTube API
    //         $apiKey = config('services.youtube.api_key');
    //         $response = Http::get("https://www.googleapis.com/youtube/v3/videos", [
    //             'part' => 'contentDetails',
    //             'id' => $videoId,
    //             'key' => $apiKey,
    //         ]);

    //         if ($response->successful()) {
    //             $data = $response->json();
    //             if (isset($data['items'][0]['contentDetails']['duration'])) {
    //                 $duration = $this->convertYouTubeDuration($data['items'][0]['contentDetails']['duration']);
    //                 return $duration;
    //             }
    //         }

    //         return null;
    //     } catch (\Exception $e) {
    //         return null;
    //     }
    // }

    /**
     * تحويل مدة يوتيوب (PT1H2M30S) إلى ثواني
     */
    // private function convertYouTubeDuration($duration)
    // {
    //     $pattern = '/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/';
    //     preg_match($pattern, $duration, $matches);

    //     $hours = isset($matches[1]) ? (int) $matches[1] : 0;
    //     $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
    //     $seconds = isset($matches[3]) ? (int) $matches[3] : 0;

    //     return ($hours * 3600) + ($minutes * 60) + $seconds;
    // }

}

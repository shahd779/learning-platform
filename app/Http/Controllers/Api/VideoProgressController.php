<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\VideoProgress;
use App\Models\Video;
use Illuminate\Support\Facades\Validator;

class VideoProgressController extends Controller
{
    /**
     * حفظ تقدم الفيديو (لأي مستخدم)
     */
    public function updateProgress(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video_id' => 'required|exists:videos,id',
            'last_position' => 'required|integer|min:0',
            'duration' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = auth()->id();

        // حساب النسبة المئوية
        $progressPercentage = 0;
        if ($request->duration && $request->duration > 0) {
            $progressPercentage = round(($request->last_position / $request->duration) * 100);
        }

        $isCompleted = $progressPercentage >= 90;

        $progress = VideoProgress::updateOrCreate(
            [
                'video_id' => $request->video_id,
                'user_id' => $userId, // ✅ أي مستخدم (طالب أو أدمن)
            ],
            [
                'last_position' => $request->last_position,
                'progress_percentage' => $progressPercentage,
                'is_completed' => $isCompleted,
                'last_watched_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ تقدم الفيديو',
            'data' => $progress
        ]);
    }

    /**
     * جلب تقدم الفيديو للمستخدم الحالي
     */
    public function getProgress($videoId)
    {
        $userId = auth()->id();

        $progress = VideoProgress::where('video_id', $videoId)
            ->where('user_id', $userId)
            ->first();

        if (!$progress) {
            return response()->json([
                'success' => true,
                'data' => [
                    'last_position' => 0,
                    'progress_percentage' => 0,
                    'is_completed' => false,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'last_position' => $progress->last_position,
                'progress_percentage' => $progress->progress_percentage,
                'is_completed' => $progress->is_completed,
                'last_watched_at' => $progress->last_watched_at,
            ]
        ]);
    }

    /**
     * جلب كل الفيديوهات وتقدمهم للمستخدم الحالي
     */
    public function getAllProgress()
{
    $userId = auth()->id();

    $progress = VideoProgress::where('user_id', $userId)
        ->with('video')
        ->orderBy('updated_at', 'desc')
        ->get();

    // ✅ لو مفيش بيانات، نرجع array فاضي مع رسالة
    if ($progress->isEmpty()) {
        return response()->json([
            'success' => true,
            'message' => 'لا يوجد تقدم فيديوهات حتى الآن',
            'data' => []
        ]);
    }

    return response()->json([
        'success' => true,
        'data' => $progress->map(function ($item) {
            return [
                'video_id' => $item->video_id,
                'video_title' => $item->video->title ?? null,
                'last_position' => $item->last_position,
                'progress_percentage' => $item->progress_percentage,
                'is_completed' => $item->is_completed,
                'last_watched_at' => $item->last_watched_at,
            ];
        })
    ]);
}

    /**
     * تحديث الفيديو كمكتمل
     */
    public function markAsCompleted($videoId)
    {
        $userId = auth()->id();

        $progress = VideoProgress::updateOrCreate(
            [
                'video_id' => $videoId,
                'user_id' => $userId,
            ],
            [
                'progress_percentage' => 100,
                'last_position' => 0,
                'is_completed' => true,
                'last_watched_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث الفيديو كمكتمل',
            'data' => $progress
        ]);
    }
}
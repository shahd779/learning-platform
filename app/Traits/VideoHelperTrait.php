<?php

namespace App\Traits;

use App\Models\VideoProgress;
use App\Models\Subject;
use App\Models\Grade;

trait VideoHelperTrait
{
    /**
     * تنسيق بيانات الفيديو
     */
    protected function formatVideoData($video)
    {
        $viewsCount = $video->views_count ?? 0;
        $watchCount = VideoProgress::where('video_id', $video->id)->count();
        $reWatchCount = max(0, $watchCount - 1);

        return [
            'id' => $video->id,
            'title' => $video->title,
            'description' => $video->description,
            'video_url' => $video->video_path ? asset('storage/' . $video->video_path) : null,
            'thumbnail' => $video->thumbnail ? asset('storage/' . $video->thumbnail) : null,
            'duration' => $video->duration,
            'duration_formatted' => $video->duration ? $this->formatDuration($video->duration) : null,
            'subject' => [
                'id' => $video->subject->id ?? null,
                'name' => $video->subject->name ?? null,
            ],
            'grade' => [
                'id' => $video->teacherSubjectGrade->grade->id ?? null,
                'name' => $video->teacherSubjectGrade->grade->name ?? null,
            ],
            'views_count' => $viewsCount,
            're_watch_count' => $reWatchCount,
            'status' => $this->getVideoStatus($video),
            'status_label' => $this->getVideoStatusLabel($video),
            'is_active' => $video->is_active,
            'is_available' => $video->is_available,
            'available_until' => $video->available_until ? $video->available_until->format('Y-m-d H:i:s') : null,
            'created_at' => $video->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * تنسيق بيانات الملف
     */
    protected function formatFileData($file)
    {
        return [
            'id' => $file->id,
            'title' => $file->title,
            'description' => $file->description,
            'file_url' => $file->file_path ? asset('storage/' . $file->file_path) : null,
            'file_type' => $file->file_type,
            'file_type_name' => $file->file_type_name,
            'file_size' => $file->file_size . ' KB',
            'file_size_mb' => $file->file_size_mb . ' MB',
            'downloads_count' => $file->downloads_count,
            'is_active' => $file->is_active,
            'is_downloadable' => $file->is_downloadable,
            'subject' => [
                'id' => $file->subject->id ?? null,
                'name' => $file->subject->name ?? null,
            ],
            'grade' => [
                'id' => $file->teacherSubjectGrade->grade->id ?? null,
                'name' => $file->teacherSubjectGrade->grade->name ?? null,
            ],
            'created_at' => $file->created_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * جلب حالة الفيديو
     */
    protected function getVideoStatus($video): string
    {
        if (!$video->is_active) {
            return 'inactive';
        }

        if ($video->available_until && $video->available_until <= now()->addDays(2)) {
            return 'expiring_soon';
        }

        if ($video->available_until && $video->available_until <= now()) {
            return 'teacher_only';
        }

        return 'available';
    }

    /**
     * جلب التسمية العربية لحالة الفيديو
     */
    protected function getVideoStatusLabel($video): string
    {
        $labels = [
            'available' => 'متاح',
            'expiring_soon' => 'سينتهي خلال يومين',
            'teacher_only' => 'متاح للمدرس فقط',
            'inactive' => 'غير مفعل',
        ];

        return $labels[$this->getVideoStatus($video)] ?? $video->status;
    }

    /**
     * تنسيق المدة
     */
    protected function formatDuration(int $seconds): string
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
     * جلب خيارات الفلترة (المواد والصفوف)
     */
    protected function getFilterOptions()
    {
        return [
            'subjects' => Subject::where('is_active', true)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'grades' => Grade::select('id', 'name')
                ->orderBy('name')
                ->get(),
        ];
    }
}
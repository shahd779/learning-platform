<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Events\NewNotificationEvent;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_subject_grade_id',
        'teacher_id',
        'subject_id',
        'title',
        'description',
        'video_path',
        'thumbnail',
        'duration',
        'order',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'views_count',
        'is_published',
        'is_active',
        'is_available',
        'available_until',
        'max_watch_count',
    ];

    protected $casts = [
        'duration' => 'integer',
        'views_count' => 'integer',
        'order' => 'integer',
        'is_published' => 'boolean',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'available_until' => 'datetime',
    ];

    // العلاقات
    public function teacherSubjectGrade()
    {
        return $this->belongsTo(TeacherSubjectGrade::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function progress()
    {
        return $this->hasMany(VideoProgress::class);
    }

    // ============= Helper Methods =============

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }


    public function isPublished(): bool
    {
        return $this->is_published && $this->status === 'approved';
    }

    // ============= دوال إرسال الإشعارات =============

    /**
     * إرسال إشعار للمدرسين (الأدمن) عند رفع فيديو جديد
     */
    public function notifyAdminsForNewVideo()
    {
        // جلب كل الأدمنة
        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        foreach ($admins as $admin) {
            $notification = Notification::create([
                'user_id' => $admin->id,
                'triggered_by_id' => $this->teacher_id,
                'type' => 'video_uploaded',
                'message' => "قام المدرس {$this->teacher->name} برفع فيديو جديد: {$this->title}",
                'data' => [
                    'video_id' => $this->id,
                    'video_title' => $this->title,
                    'teacher_name' => $this->teacher->name,
                    'teacher_id' => $this->teacher_id,
                    'subject_name' => $this->subject->name,
                    'status' => 'pending',
                ],
                'is_read' => false,
            ]);

            // بث الإشعار فوراً عبر WebSocket
            broadcast(new NewNotificationEvent($notification));
        }
    }

    /**
     * إرسال إشعار للمدرس عند قبول فيديو
     */
    public function notifyTeacherForApproval()
    {
        $notification = Notification::create([
            'user_id' => $this->teacher_id,
            'triggered_by_id' => $this->reviewed_by,
            'type' => 'video_approved',
            'message' => "تم قبول فيديو: {$this->title} ونشره للطلاب",
            'data' => [
                'video_id' => $this->id,
                'video_title' => $this->title,
                'reviewer_name' => $this->reviewer?->name ?? 'الأدمن',
                'status' => 'approved',
                'is_published' => true,
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotificationEvent($notification));
    }

    /**
     * إرسال إشعار للمدرس عند رفض فيديو
     */
    public function notifyTeacherForRejection()
    {
        $notification = Notification::create([
            'user_id' => $this->teacher_id,
            'triggered_by_id' => $this->reviewed_by,
            'type' => 'video_rejected',
            'message' => "تم رفض فيديو: {$this->title}",
            'data' => [
                'video_id' => $this->id,
                'video_title' => $this->title,
                'reviewer_name' => $this->reviewer?->name ?? 'الأدمن',
                'rejection_reason' => $this->rejection_reason,
                'status' => 'rejected',
            ],
            'is_read' => false,
        ]);

        broadcast(new NewNotificationEvent($notification));
    }
    // app/Models/Video.php



// هل الفيديو متاح للمشاهدة؟
public function isAccessible()
{
    if (!$this->is_active) return false;
    if (!$this->is_available) return false;
    if ($this->available_until && $this->available_until < now()) return false;
    return true;
}

// هل الطالب يقدر يشاهد الفيديو (لم يتجاوز عدد المشاهدات)؟
public function canWatch($studentId)
{
    if ($this->max_watch_count === null) return true;
    
    $watchCount = VideoProgress::where('video_id', $this->id)
        ->where('user_id', $studentId)
        ->count();
    
    return $watchCount < $this->max_watch_count;
}

// جلب عدد المشاهدات المتبقية للطالب
public function getRemainingWatches($studentId)
{
    if ($this->max_watch_count === null) return null;
    
    $watchCount = VideoProgress::where('video_id', $this->id)
        ->where('user_id', $studentId)
        ->count();
    
    return max(0, $this->max_watch_count - $watchCount);
}

}
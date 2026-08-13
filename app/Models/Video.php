<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
        'youtube_url',
        'thumbnail',
        'duration',
        'order',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'views_count',
        'is_published',
    ];

    protected $casts = [
        'duration' => 'integer',
        'views_count' => 'integer',
        'order' => 'integer',
        'is_published' => 'boolean',
        'reviewed_at' => 'datetime',
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

    // Helper Methods
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

    public function isRevision(): bool
    {
        return $this->status === 'revision';
    }

    public function isPublished(): bool
    {
        return $this->is_published && $this->status === 'approved';
    }
}
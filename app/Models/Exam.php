<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_subject_grade_id',
        'teacher_id',
        'title',
        'description',
        'questions',
        'total_marks',
        'start_at',
        'end_at',
        'status',
    ];

    protected $casts = [
        'questions' => 'array',
        'total_marks' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function teacherSubjectGrade()
    {
        return $this->belongsTo(TeacherSubjectGrade::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function results()
    {
        return $this->hasMany(ExamResult::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isActive(): bool
    {
        return $this->isPublished() && 
               ($this->start_at === null || $this->start_at <= now()) &&
               ($this->end_at === null || $this->end_at >= now());
    }
}
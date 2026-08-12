<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StudentSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'teacher_subject_grade_id',
        'status',
        'subscribed_at',
        'expires_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacherSubjectGrade()
    {
        return $this->belongsTo(TeacherSubjectGrade::class);
    }

    public function isActive()
    {
        return $this->status === 'active' && 
               ($this->expires_at === null || $this->expires_at > now());
    }
}
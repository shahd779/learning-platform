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
        'access_code',
        'status',
        'subscribed_at',
        'expires_at',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * العلاقة مع الطالب
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * العلاقة مع المادة (من خلال TeacherSubjectGrade)
     */
    public function teacherSubjectGrade()
    {
        return $this->belongsTo(TeacherSubjectGrade::class);
    }

    /**
     * المادة
     */
    public function subject()
    {
        return $this->hasOneThrough(
            Subject::class,
            TeacherSubjectGrade::class,
            'id',
            'id',
            'teacher_subject_grade_id',
            'subject_id'
        );
    }

    /**
     * التحقق من أن الاشتراك نشط
     */
    public function isActive()
    {
        return $this->status === 'active' && 
               ($this->expires_at === null || $this->expires_at > now());
    }
}
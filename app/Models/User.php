<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'phone',
        'password',
        'image',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
    ];

    // ============= العلاقات =============

    // العلاقة بجدول teacher_subject_grade (المدرس)
    public function teacherSubjectGrades()
    {
        return $this->hasMany(TeacherSubjectGrade::class, 'teacher_id');
    }

    // المواد اللي المدرس بيدرسها
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject_grade', 'teacher_id', 'subject_id')
                    ->withPivot('grade_id', 'access_code', 'is_active')
                    ->withTimestamps();
    }

    // الصفوف اللي المدرس بيدرس فيها
    public function grades()
    {
        return $this->belongsToMany(Grade::class, 'teacher_subject_grade', 'teacher_id', 'grade_id')
                    ->withPivot('subject_id', 'access_code', 'is_active')
                    ->withTimestamps();
    }

    // اشتراكات الطالب
    public function studentSubscriptions()
    {
        return $this->hasMany(StudentSubscription::class, 'student_id');
    }

    // المواد اللي الطالب مشترك فيها
    public function enrolledSubjects()
    {
        return $this->belongsToMany(TeacherSubjectGrade::class, 'student_subscriptions', 'student_id', 'teacher_subject_grade_id')
                    ->withPivot('status', 'subscribed_at', 'expires_at')
                    ->withTimestamps();
    }

    // الفيديوهات (للمدرس)
    public function videos()
    {
        return $this->hasMany(Video::class, 'teacher_id');
    }

    // الواجبات (للمدرس)
    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'teacher_id');
    }

    // الامتحانات (للمدرس)
    public function exams()
    {
        return $this->hasMany(Exam::class, 'teacher_id');
    }

    // المدفوعات (للطالب)
    public function payments()
    {
        return $this->hasMany(Payment::class, 'student_id');
    }

    // الشكاوى
    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'user_id');
    }

    // الردود على الشكاوى
    public function complaintReplies()
    {
        return $this->hasMany(ComplaintReply::class, 'user_id');
    }

    // ============= Helper Methods =============

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return asset('images/default-avatar.png');
    }
     public function currentGrade()
    {
        return $this->hasOneThrough(
            Grade::class,
            StudentSubscription::class,
            'student_id',          // Foreign key on student_subscriptions
            'id',                  // Local key on grades
            'id',                  // Local key on users
            'teacher_subject_grade_id' // Local key on student_subscriptions
        )
        ->where('student_subscriptions.status', 'active')
        ->join('teacher_subject_grade', 'student_subscriptions.teacher_subject_grade_id', '=', 'teacher_subject_grade.id')
        ->select('grades.*')
        ->latest('student_subscriptions.created_at')
        ->limit(1);
    }

    /**
     * جلب اسم الصف الحالي (Attribute)
     */
    public function getCurrentGradeNameAttribute()
    {
        $grade = $this->currentGrade()->first();
        return $grade ? $grade->name : null;
    }

}
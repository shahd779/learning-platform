<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ============= العلاقات الأساسية =============

    /**
     * المواد اللي المدرس بيدرسها (عن طريق جدول teacher_subject_grade)
     */
    public function teacherSubjects()
    {
        return $this->hasMany(TeacherSubjectGrade::class, 'teacher_id');
    }

    /**
     * المواد اللي المدرس بيدرسها (مباشر)
     */
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject_grade', 'teacher_id', 'subject_id')
                    ->withPivot('grade_id', 'access_code', 'is_active')
                    ->withTimestamps();
    }

    /**
     * الصفوف اللي المدرس بيدرس فيها
     */
    public function grades()
    {
        return $this->belongsToMany(Grade::class, 'teacher_subject_grade', 'teacher_id', 'grade_id')
                    ->withPivot('subject_id', 'access_code', 'is_active')
                    ->withTimestamps();
    }

    /**
     * الاشتراكات (للطلاب)
     */
    public function subscriptions()
    {
        return $this->hasMany(StudentSubscription::class, 'student_id');
    }

    /**
     * المواد اللي الطالب مشترك فيها
     */
    public function enrolledSubjects()
    {
        return $this->belongsToMany(Subject::class, 'student_subscriptions', 'student_id', 'teacher_subject_grade_id')
                    ->withPivot('status', 'subscribed_at', 'expires_at')
                    ->withTimestamps();
    }

    /**
     * طلبات المدرس
     */
    public function teacherRequests()
    {
        return $this->hasMany(TeacherRequest::class, 'teacher_id');
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
}
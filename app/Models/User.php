<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; // مهم للـ API

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens; // أضفنا HasApiTokens

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone', // أضفنا phone
        'password',
        'image',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // ============= العلاقات =============

    /**
     * العلاقة مع المواد (المدرس)
     */
    public function subjects()
    {
        return $this->hasMany(Subject::class, 'teacher_id');
    }

    /**
     * العلاقة مع الاشتراكات (الطالب)
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'student_id');
    }

    /**
     * العلاقة مع الصفوف (المدرس)
     */
    public function grades()
    {
        return $this->hasMany(Grade::class, 'teacher_id');
    }

    // ============= Helper Methods =============

    /**
     * التحقق من أن المستخدم أدمن
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * التحقق من أن المستخدم مدرس
     */
    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    /**
     * التحقق من أن المستخدم طالب
     */
    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /**
     * التحقق من أن الحساب نشط
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * جلب صورة المستخدم (مع رابط كامل)
     */
    public function getImageUrlAttribute(): string
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return asset('images/default-avatar.png'); // صورة افتراضية
    }

    public function assignments()
{
    return $this->hasMany(Assignment::class, 'student_id');
}

public function examResults()
{
    return $this->hasMany(ExamResult::class, 'student_id');
}

public function videoProgress()
{
    return $this->hasMany(VideoProgress::class, 'student_id');
}

public function teachingContents()
{
    return $this->hasManyThrough(Content::class, Subject::class, 'teacher_id');
}
}
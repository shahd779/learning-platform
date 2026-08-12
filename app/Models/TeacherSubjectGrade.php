<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeacherSubjectGrade extends Model
{
    use HasFactory;

    protected $table = 'teacher_subject_grade';

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'grade_id',
        'access_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(StudentSubscription::class, 'teacher_subject_grade_id');
    }

    public function studentsCount()
    {
        return $this->subscriptions()->where('status', 'active')->count();
    }
}
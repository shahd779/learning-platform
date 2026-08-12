<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'teacher_subject_grade', 'grade_id', 'subject_id')
                    ->withPivot('teacher_id', 'access_code', 'is_active')
                    ->withTimestamps();
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'teacher_subject_grade', 'grade_id', 'teacher_id')
                    ->withPivot('subject_id', 'access_code', 'is_active')
                    ->withTimestamps();
    }
}
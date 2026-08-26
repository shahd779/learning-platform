<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_subject_grade_id',
        'teacher_id',
        'subject_id',
        'title',
        'description',
        'file_path',
        'file_type',
        'file_size',
        'downloads_count',
        'is_active',
        'is_downloadable',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_downloadable' => 'boolean',
    ];

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

    public function isAccessible()
    {
        return $this->is_active && $this->is_downloadable;
    }
}
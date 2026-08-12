<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'teacher_subject_grade', 'subject_id', 'teacher_id')
                    ->withPivot('grade_id', 'access_code', 'is_active')
                    ->withTimestamps();
    }

    public function grades()
    {
        return $this->belongsToMany(Grade::class, 'teacher_subject_grade', 'subject_id', 'grade_id')
                    ->withPivot('teacher_id', 'access_code', 'is_active')
                    ->withTimestamps();
    }

    public function subscriptions()
    {
        return $this->hasMany(StudentSubscription::class);
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }
}
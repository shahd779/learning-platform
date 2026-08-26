<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TeacherSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'videos_active_by_default',
        'videos_availability',
        'videos_availability_days',
        'videos_max_watch_count',
        'files_active_by_default',
        'files_downloadable_by_default',
    ];

    protected $casts = [
        'videos_active_by_default' => 'boolean',
        'files_active_by_default' => 'boolean',
        'files_downloadable_by_default' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    // جلب الإعدادات الافتراضية للفيديوهات
    public function getDefaultVideoSettings()
    {
        return [
            'is_active' => $this->videos_active_by_default,
            'is_available' => $this->videos_availability === 'always',
            'available_until' => $this->videos_availability === 'limited' 
                ? now()->addDays($this->videos_availability_days) 
                : null,
            'max_watch_count' => $this->videos_max_watch_count,
        ];
    }

    // جلب الإعدادات الافتراضية للملفات
    public function getDefaultFileSettings()
    {
        return [
            'is_active' => $this->files_active_by_default,
            'is_downloadable' => $this->files_downloadable_by_default,
        ];
    }
}
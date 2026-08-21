<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VideoProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'user_id',
        'progress_percentage',
        'last_position',
        'is_completed',
        'last_watched_at',
    ];

    protected $casts = [
        'progress_percentage' => 'integer',
        'last_position' => 'integer',
        'is_completed' => 'boolean',
        'last_watched_at' => 'datetime',
    ];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
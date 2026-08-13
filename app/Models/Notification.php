<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'triggered_by_id',
        'type',
        'message',
        'data',
        'is_read',
    ];

    protected $casts = [
        'data' => 'array', // عشان تخلي الـ JSON يتحول لـ Array أو Object أوتوماتيك لما تجيبه
        'is_read' => 'boolean',
    ];

    // علاقة: الإشعار ده لمين؟
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // علاقة: مين السبب في الإشعار ده؟
    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ComplaintReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id',
        'user_id',
        'sender_type',
        'message',
        'attachment',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FileDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'file_id',
        'student_id',
        'downloaded_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'downloaded_at' => 'datetime',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
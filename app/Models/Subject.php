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
        'teacher_id',
        'grade_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function contents()
{
    return $this->hasMany(Content::class)->orderBy('order');
}

public function packages()
{
    return $this->hasMany(Package::class);
}
}
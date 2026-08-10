<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    //




    public function subject()
{
    return $this->belongsTo(Subject::class);
}

public function assignments()
{
    return $this->hasMany(Assignment::class);
}

public function examResults()
{
    return $this->hasMany(ExamResult::class);
}

public function videoProgress()
{
    return $this->hasMany(VideoProgress::class);
}

// هل المحتوى واجب؟
public function isAssignment(): bool
{
    return $this->type === 'assignment';
}

// هل المحتوى امتحان؟
public function isExam(): bool
{
    return $this->type === 'exam';
}

// هل المحتوى فيديو؟
public function isVideo(): bool
{
    return $this->type === 'video';
}
}

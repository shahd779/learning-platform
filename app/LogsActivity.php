<?php

namespace App;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected function logActivity($activity, $description, $type = 'general')
    {
        $user = Auth::user();
        
        if (!$user) return;
        
        ActivityLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'activity' => $activity,
            'description' => $description,
            'type' => $type,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
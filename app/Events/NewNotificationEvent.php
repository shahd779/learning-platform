<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // لازم تخليها تنبعث فوراً
use Illuminate\Foundation\Events\Dispatchable;

class NewNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public $notification; // هنبعث الإشعار ده كامل للفرونت

    public function __construct($notification)
    {
        $this->notification = $notification;
    }

    // الإشعار هيروح لقناة خاصة بالمستخدم (الأدمن أو المدرس)
    public function broadcastOn()
    {
        // القناة هتكون اسمها user.{ID} (مثلاً user.5)
        return new Channel('user.' . $this->notification->user_id);
    }

    // اسم الحدث اللي الفرونت هيسمعله
    public function broadcastAs()
    {
        return 'new-notification';
    }
}
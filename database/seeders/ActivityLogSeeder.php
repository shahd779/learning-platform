<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActivityLog;
use App\Models\User;
use Carbon\Carbon;

class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        // جلب المستخدمين
        $users = User::all();
        
        if ($users->isEmpty()) {
            $this->command->info('❌ لا يوجد مستخدمين. قم بتشغيل seeders المستخدمين أولاً.');
            return;
        }

        // أنواع الأنشطة
        $activities = [
            'login' => ['تسجيل دخول', 'تم تسجيل دخول المستخدم {user} إلى المنصة'],
            'logout' => ['تسجيل خروج', 'تم تسجيل خروج المستخدم {user} من المنصة'],
            'create' => ['إضافة مستخدم جديد', 'تم إضافة مستخدم جديد بواسطة {user}'],
            'update' => ['تعديل بيانات مستخدم', 'تم تعديل بيانات مستخدم بواسطة {user}'],
            'delete' => ['حذف مستخدم', 'تم حذف مستخدم بواسطة {user}'],
            'ban' => ['حظر مستخدم', 'تم حظر مستخدم بواسطة {user}'],
            'unban' => ['فك الحظر', 'تم فك الحظر عن مستخدم بواسطة {user}'],
            'subscription' => ['إدارة اشتراكات', 'تم تعديل اشتراك بواسطة {user}'],
            'payment' => ['إدارة مدفوعات', 'تم معالجة دفعة بواسطة {user}'],
            'content' => ['إدارة محتوى', 'تم تعديل محتوى بواسطة {user}'],
        ];

        $activityTypes = array_keys($activities);
        
        // إنشاء 100 نشاط عشوائي
        for ($i = 0; $i < 100; $i++) {
            $user = $users->random();
            $type = $activityTypes[array_rand($activityTypes)];
            $activityData = $activities[$type];
            
            $activity = $activityData[0];
            $description = str_replace('{user}', $user->name, $activityData[1]);
            
            // تاريخ عشوائي خلال الـ 30 يوم الماضية
            $randomDate = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            
            ActivityLog::create([
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_role' => $user->role,
                'activity' => $activity,
                'description' => $description,
                'type' => $type,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'created_at' => $randomDate,
                'updated_at' => $randomDate,
            ]);
        }

        $this->command->info('✅ تم إنشاء 100 نشاط تجريبي بنجاح');
    }
}
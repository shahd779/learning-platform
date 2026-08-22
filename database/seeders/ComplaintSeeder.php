<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Complaint;
use App\Models\ComplaintReply;
use App\Models\User;
use Illuminate\Support\Str;

class ComplaintSeeder extends Seeder
{
    public function run(): void
    {
        // جلب المستخدمين (طلاب ومدرسين)
        $users = User::whereIn('role', ['student', 'teacher'])->get();

        if ($users->isEmpty()) {
            $this->command->warn('⚠️ لا يوجد مستخدمين لإضافة شكاوى');
            return;
        }

        // جلب الأدمن
        $admins = User::where('role', 'admin')->get();
        $admin = $admins->first();

        $subjects = [
            'مشكلة في تسجيل الدخول',
            'صوت الفيديو غير واضح',
            'مشكلة في تحميل المحاضرة',
            'لا يمكن رفع الواجب',
            'لم يتم استرداد المبلغ',
            'المنصة بطيئة جداً',
            'سلوك غير لائق من أحد المستخدمين',
            'محتوى غير مناسب',
            'مشكلة في الكود',
            'مشاكل في الدفع',
            'الدرس غير مكتمل',
            'رابط الفيديو لا يعمل',
        ];

        $descriptions = [
            'عند محاولة تسجيل الدخول، تظهر لي رسالة خطأ ولا أستطيع الدخول إلى حسابي.',
            'الصوت في الفيديو يقطع باستمرار والشروحات غير واضحة.',
            'عند محاولة تحميل المحاضرة، تظهر رسالة فشل في التحميل.',
            'لا يمكنني رفع الواجب، تظهر رسالة خطأ في الملف.',
            'تم خصم المبلغ من حسابي ولكن الاشتراك لم يتم تفعيله.',
            'المنصة تتعطل باستمرار وتحتاج وقت طويل للتحميل.',
            'أحد المستخدمين يقوم بسلوك غير لائق في التعليقات.',
            'يوجد محتوى غير مناسب للفئة العمرية.',
            'الكود الخاص بالمادة لا يعمل عند محاولة التسجيل.',
            'مشكلة في عملية الدفع، المبلغ تم خصمه ولكن الاشتراك لم يتفعل.',
            'الدرس غير مكتمل، يوجد جزء ناقص من الشرح.',
            'رابط الفيديو لا يعمل ويظهر خطأ 404.',
        ];

        // =============================================

        for ($i = 0; $i < 15; $i++) {
            $user = $users->random();
            $subjectIndex = array_rand($subjects);

            $complaint = Complaint::create([
                'code' => 'CM-' . now()->format('Y') . '-' . str_pad(Complaint::count() + 1, 4, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'type' => ['general', 'code', 'payment'][array_rand(['general', 'code', 'payment'])],
                'subject' => $subjects[$subjectIndex],
                'description' => $descriptions[$subjectIndex],
                'attachment' => rand(0, 1) ? 'complaints/screenshot_' . rand(1, 10) . '.png' : null,
                'status' => 'pending',
                'created_at' => now()->subDays(rand(0, 30)),
            ]);

            // إضافة ردود (أحياناً)
            if (rand(0, 1)) {
                ComplaintReply::create([
                    'complaint_id' => $complaint->id,
                    'user_id' => $user->id,
                    'sender_type' => 'user',
                    'message' => 'أرجو الرد على شكواي في أقرب وقت',
                    'created_at' => $complaint->created_at->addHours(rand(1, 5)),
                ]);
            }
        }

 

        for ($i = 0; $i < 10; $i++) {
            $user = $users->random();
            $subjectIndex = array_rand($subjects);

            $complaint = Complaint::create([
                'code' => 'CM-' . now()->format('Y') . '-' . str_pad(Complaint::count() + 1, 4, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'type' => ['general', 'code', 'payment'][array_rand(['general', 'code', 'payment'])],
                'subject' => $subjects[$subjectIndex],
                'description' => $descriptions[$subjectIndex],
                'attachment' => rand(0, 1) ? 'complaints/screenshot_' . rand(1, 10) . '.png' : null,
                'status' => 'in_progress',
                'created_at' => now()->subDays(rand(0, 20)),
            ]);

            // رد من المستخدم
            ComplaintReply::create([
                'complaint_id' => $complaint->id,
                'user_id' => $user->id,
                'sender_type' => 'user',
                'message' => 'أرجو حل المشكلة في أقرب وقت',
                'created_at' => $complaint->created_at->addHours(rand(1, 3)),
            ]);

            // رد من الأدمن
            if ($admin) {
                ComplaintReply::create([
                    'complaint_id' => $complaint->id,
                    'user_id' => $admin->id,
                    'sender_type' => 'admin',
                    'message' => 'جاري التحقق من المشكلة، سأتواصل معك قريباً',
                    'created_at' => $complaint->created_at->addHours(rand(4, 10)),
                ]);
            }

            // رد آخر من المستخدم (أحياناً)
            if (rand(0, 1)) {
                ComplaintReply::create([
                    'complaint_id' => $complaint->id,
                    'user_id' => $user->id,
                    'sender_type' => 'user',
                    'message' => 'شكراً للرد، أرجو إعلامي عند الحل',
                    'created_at' => $complaint->created_at->addHours(rand(11, 20)),
                ]);
            }
        }

    

        for ($i = 0; $i < 8; $i++) {
            $user = $users->random();
            $subjectIndex = array_rand($subjects);
            $resolvedAt = now()->subDays(rand(0, 15));

            $complaint = Complaint::create([
                'code' => 'CM-' . now()->format('Y') . '-' . str_pad(Complaint::count() + 1, 4, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'type' => ['general', 'code', 'payment'][array_rand(['general', 'code', 'payment'])],
                'subject' => $subjects[$subjectIndex],
                'description' => $descriptions[$subjectIndex],
                'attachment' => rand(0, 1) ? 'complaints/screenshot_' . rand(1, 10) . '.png' : null,
                'status' => 'resolved',
                'admin_response' => 'تم حل المشكلة بنجاح، يرجى التأكد من ذلك',
                'resolved_by' => $admin ? $admin->id : null,
                'resolved_at' => $resolvedAt,
                'created_at' => $resolvedAt->copy()->subDays(rand(1, 10)),
            ]);

            // سلسلة ردود
            $replies = [
                ['user', 'أرجو حل المشكلة'],
                ['admin', 'جاري التحقق من المشكلة'],
                ['user', 'شكراً للاهتمام'],
                ['admin', 'تم حل المشكلة، يرجى التأكد'],
                ['user', 'شكراً جزيلاً، تم حل المشكلة'],
            ];

            $replyTime = $complaint->created_at;
            foreach ($replies as $index => $reply) {
                if (rand(0, 1) || $index < 3) {
                    $replyTime = $replyTime->addHours(rand(1, 6));
                    
                    ComplaintReply::create([
                        'complaint_id' => $complaint->id,
                        'user_id' => $reply[0] === 'user' ? $user->id : ($admin ? $admin->id : null),
                        'sender_type' => $reply[0],
                        'message' => $reply[1],
                        'created_at' => $replyTime,
                    ]);
                }
            }
        }

   

        for ($i = 0; $i < 3; $i++) {
            $user = $users->random();
            $subjectIndex = array_rand($subjects);

            Complaint::create([
                'code' => 'CM-' . now()->format('Y') . '-' . str_pad(Complaint::count() + 1, 4, '0', STR_PAD_LEFT),
                'user_id' => $user->id,
                'type' => 'general',
                'subject' => $subjects[$subjectIndex],
                'description' => $descriptions[$subjectIndex],
                'attachment' => null,
                'status' => 'pending',
                'created_at' => now(),
            ]);
        }

 
    }
}
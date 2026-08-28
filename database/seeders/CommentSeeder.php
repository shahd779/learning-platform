<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Comment;
use App\Models\User;
use App\Models\Video;
use Illuminate\Support\Str;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        // جلب الطلاب
        $students = User::where('role', 'student')->get();

        // جلب الفيديوهات المتوافق عليها
        $videos = Video::where('status', 'approved')->get();

        if ($students->isEmpty() || $videos->isEmpty()) {
            $this->command->warn('⚠️ لا يوجد طلاب أو فيديوهات متوافق عليها لإضافة تعليقات');
            return;
        }

        $commentContents = [
            'شرح ممتاز، شكراً لك',
            'أرجو توضيح النقطة الأخيرة',
            'فهمت الدرس جيداً',
            'هل يمكن إعادة شرح المثال الثالث؟',
            'ممتاز جداً، استمر',
            'كان هناك بعض الصعوبة في الفهم',
            'شكراً على الشرح الوافي',
            'أحتاج المزيد من الأمثلة',
            'درس رائع، استفدت كثيراً',
            'هل يوجد واجب على هذا الدرس؟',
            'الصوت غير واضح في بعض الأجزاء',
            'الفيديو ممتاز لكنه طويل بعض الشيء',
            'أرجو شرح أسرع في الدروس القادمة',
            'ما هي أفضل طريقة لحل هذه المسائل؟',
            'شكراً جزيلاً على المجهود',
            'لماذا تم حذف الدرس السابق؟',
            'متى سيتم نشر الدرس التالي؟',
            'اللوحة غير واضحة في الدقيقة 5',
            'أحب طريقة الشرح، استمر',
            'هل هناك مراجع إضافية للدرس؟',
        ];

        $replies = [
            'شكراً على ملاحظتك، سأوضحها في الدرس القادم',
            'تمت الإضافة، شكراً للتنبيه',
            'سأعيد شرح النقطة في الفيديو القادم',
            'شكراً لك، سأحاول تحسين الصوت',
            'تم تعديل الواجب، شكراً',
            'شكراً على سؤالك، سأجيب عليه قريباً',
        ];



        foreach (range(1, 60) as $i) {
            $student = $students->random();
            $video = $videos->random();

            Comment::create([
                'content' => $commentContents[array_rand($commentContents)],
                'user_id' => $student->id,
                'video_id' => $video->id,
                'parent_id' => null,
                'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
            ]);
        }


        for ($i = 0; $i < 20; $i++) {
            $student = $students->random();
            $video = $videos->random();

            // تعليق رئيسي
            $parentComment = Comment::create([
                'content' => $commentContents[array_rand($commentContents)],
                'user_id' => $student->id,
                'video_id' => $video->id,
                'parent_id' => null,
                'created_at' => now()->subDays(rand(0, 15))->subHours(rand(0, 23)),
            ]);

            // عدد الردود (1-3)
            $numReplies = rand(1, 3);

            for ($j = 0; $j < $numReplies; $j++) {
                // الرد من طالب آخر أو من المدرس
                $replyUser = rand(0, 1) ? $students->random() : $video->teacher;

                Comment::create([
                    'content' => $replies[array_rand($replies)],
                    'user_id' => $replyUser->id,
                    'video_id' => $video->id,
                    'parent_id' => $parentComment->id,
                    'created_at' => $parentComment->created_at->addMinutes(rand(1, 60)),
                ]);
            }
        }



        foreach (range(1, 8) as $i) {
            $student = $students->random();
            $video = $videos->random();

            Comment::create([
                'content' => $commentContents[array_rand($commentContents)],
                'user_id' => $student->id,
                'video_id' => $video->id,
                'parent_id' => null,
                'created_at' => now()->subMinutes(rand(1, 120)),
            ]);
        }

    }
}
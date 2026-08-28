<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Video;
use App\Models\TeacherSubjectGrade;
use App\Models\User;
use Illuminate\Support\Str;

class VideoSeeder extends Seeder
{
    public function run(): void
    {
        // جلب روابط المدرسين (teacher_subject_grade)
        $teacherSubjectGrades = TeacherSubjectGrade::where('is_active', true)->get();

        if ($teacherSubjectGrades->isEmpty()) {
            $this->command->warn('⚠️ لا يوجد روابط مدرسين لإضافة فيديوهات');
            return;
        }

        // جلب المدرسين
        $teachers = User::where('role', 'teacher')->get();

        $videoTitles = [
            'الدرس الأول - مقدمة في الرياضيات',
            'الدرس الثاني - المعادلات الخطية',
            'الدرس الثالث - حل المعادلات التربيعية',
            'الدرس الرابع - الدوال',
            'الدرس الخامس - المتتابعات',
            'الدرس السادس - الهندسة التحليلية',
            'الدرس السابع - التفاضل',
            'الدرس الثامن - التكامل',
            'الدرس التاسع - المصفوفات',
            'الدرس العاشر - الاحتمالات',
        ];

        $descriptions = [
            'شرح مفصل لمقدمة الرياضيات مع أمثلة تطبيقية',
            'حل المعادلات الخطية خطوة بخطوة',
            'طرق حل المعادلات التربيعية مع أمثلة',
            'شرح الدوال وأنواعها مع رسوم بيانية',
            'المتتابعات الحسابية والهندسية',
            'الهندسة التحليلية والإحداثيات',
            'مبادئ التفاضل وقواعد الاشتقاق',
            'مقدمة في التكامل وطرق حسابه',
            'المصفوفات وطرق حل المعادلات بها',
            'الاحتمالات والقوانين الأساسية',
        ];

        $statuses = ['pending', 'approved', 'rejected'];

        // =============================================
        // فيديوهات متوافق عليها (approved)
        // =============================================
      

        foreach (range(1, 30) as $i) {
            $tsg = $teacherSubjectGrades->random();
            $teacher = $teachers->random();

            $titleIndex = array_rand($videoTitles);

            Video::create([
                'teacher_subject_grade_id' => $tsg->id,
                'teacher_id' => $tsg->teacher_id,
                'subject_id' => $tsg->subject_id,
                'title' => $videoTitles[$titleIndex] . ' (' . $i . ')',
                'description' => $descriptions[$titleIndex],
                'video_path' => 'videos/video_' . Str::random(8) . '.mp4',
                'thumbnail' => 'thumbnails/thumb_' . Str::random(8) . '.jpg',
                'duration' => rand(300, 3600), // 5 دقائق إلى ساعة
                'order' => rand(1, 10),
                'status' => 'approved',
                'is_published' => true,
                'is_active' => rand(0, 1) ? true : false,
                'is_available' => true,
                'available_until' => rand(0, 1) ? null : now()->addDays(rand(1, 30)),
                'max_watch_count' => rand(0, 1) ? null : rand(1, 5),
                'views_count' => rand(10, 500),
                'created_at' => now()->subDays(rand(0, 30)),
            ]);
        }

        // =============================================
        // فيديوهات بانتظار المراجعة (pending)
        // =============================================
      

        foreach (range(1, 15) as $i) {
            $tsg = $teacherSubjectGrades->random();
            $titleIndex = array_rand($videoTitles);

            Video::create([
                'teacher_subject_grade_id' => $tsg->id,
                'teacher_id' => $tsg->teacher_id,
                'subject_id' => $tsg->subject_id,
                'title' => $videoTitles[$titleIndex] . ' (جديد ' . $i . ')',
                'description' => $descriptions[$titleIndex],
                'video_path' => 'videos/video_' . Str::random(8) . '.mp4',
                'thumbnail' => 'thumbnails/thumb_' . Str::random(8) . '.jpg',
                'duration' => rand(300, 3600),
                'order' => rand(1, 10),
                'status' => 'pending',
                'is_published' => false,
                'is_active' => true,
                'is_available' => true,
                'available_until' => null,
                'max_watch_count' => null,
                'views_count' => 0,
                'created_at' => now()->subDays(rand(0, 10)),
            ]);
        }

        // =============================================
        // فيديوهات مرفوضة (rejected)
        // =============================================
     

        foreach (range(1, 5) as $i) {
            $tsg = $teacherSubjectGrades->random();
            $titleIndex = array_rand($videoTitles);

            Video::create([
                'teacher_subject_grade_id' => $tsg->id,
                'teacher_id' => $tsg->teacher_id,
                'subject_id' => $tsg->subject_id,
                'title' => $videoTitles[$titleIndex] . ' (مرفوض ' . $i . ')',
                'description' => $descriptions[$titleIndex],
                'video_path' => 'videos/video_' . Str::random(8) . '.mp4',
                'thumbnail' => 'thumbnails/thumb_' . Str::random(8) . '.jpg',
                'duration' => rand(300, 3600),
                'order' => rand(1, 10),
                'status' => 'rejected',
                'is_published' => false,
                'is_active' => false,
                'is_available' => false,
                'available_until' => null,
                'max_watch_count' => null,
                'views_count' => 0,
                'rejection_reason' => 'جودة الفيديو منخفضة، يرجى إعادة الرفع',
                'reviewed_by' => User::where('role', 'admin')->first()?->id,
                'reviewed_at' => now()->subDays(rand(1, 5)),
                'created_at' => now()->subDays(rand(5, 15)),
            ]);
        }

        // =============================================
        // فيديوهات اليوم (حديثة)
        // =============================================
    

        foreach (range(1, 5) as $i) {
            $tsg = $teacherSubjectGrades->random();
            $titleIndex = array_rand($videoTitles);

            Video::create([
                'teacher_subject_grade_id' => $tsg->id,
                'teacher_id' => $tsg->teacher_id,
                'subject_id' => $tsg->subject_id,
                'title' => $videoTitles[$titleIndex] . ' (اليوم ' . $i . ')',
                'description' => $descriptions[$titleIndex],
                'video_path' => 'videos/video_' . Str::random(8) . '.mp4',
                'thumbnail' => 'thumbnails/thumb_' . Str::random(8) . '.jpg',
                'duration' => rand(300, 3600),
                'order' => rand(1, 10),
                'status' => 'pending',
                'is_published' => false,
                'is_active' => true,
                'is_available' => true,
                'available_until' => null,
                'max_watch_count' => null,
                'views_count' => 0,
                'created_at' => now()->subMinutes(rand(1, 120)),
            ]);
        }

    }
}
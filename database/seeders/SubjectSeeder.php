<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\User;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        // جلب الصفوف والمدرسين
        $grades = Grade::all()->keyBy('code');
        $teachers = User::where('role', 'teacher')->get();

        // لو مفيش مدرسين، استخدمي الأدمن مؤقتاً
        $defaultTeacher = User::where('role', 'admin')->first();

        $subjects = [
            // أحمد مدرس رياضيات لجميع الصفوف
            [
                'name' => 'رياضيات',
                'code' => 'MATH101',
                'grade_code' => 'G1',
                'teacher_phone' => '01012345678', // رقم أحمد
            ],
            [
                'name' => 'رياضيات',
                'code' => 'MATH102',
                'grade_code' => 'G2',
                'teacher_phone' => '01012345678', // أحمد
            ],
            [
                'name' => 'رياضيات',
                'code' => 'MATH103',
                'grade_code' => 'G3',
                'teacher_phone' => '01098765432', // محمد
            ],
            // علي مدرس فيزياء
            [
                'name' => 'فيزياء',
                'code' => 'PHY101',
                'grade_code' => 'G1',
                'teacher_phone' => '01011111111', // علي
            ],
            [
                'name' => 'فيزياء',
                'code' => 'PHY102',
                'grade_code' => 'G2',
                'teacher_phone' => '01011111111', // علي
            ],
            // سارة مدرسة إنجليزي
            [
                'name' => 'إنجليزي',
                'code' => 'ENG101',
                'grade_code' => 'G1',
                'teacher_phone' => '01022222222', // سارة
            ],
        ];

        foreach ($subjects as $subject) {
            $teacher = User::where('phone', $subject['teacher_phone'])->first();
            
            Subject::create([
                'name' => $subject['name'],
                'code' => $subject['code'],
                'grade_id' => $grades[$subject['grade_code']]->id,
                'teacher_id' => $teacher ? $teacher->id : $defaultTeacher->id,
                'is_active' => true,
            ]);
        }
    }
}
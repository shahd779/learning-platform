<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Subject;
use App\Models\Grade;
use App\Models\User;
use App\Models\TeacherSubjectGrade;
use Illuminate\Support\Str;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        // =============================================
        // 1. جلب الصفوف والمدرسين
        // =============================================
        $grades = Grade::all()->keyBy('code');
        $teachers = User::where('role', 'teacher')->get()->keyBy('phone');

        // لو مفيش مدرسين، استخدمي الأدمن مؤقتاً
        $defaultTeacher = User::where('role', 'admin')->first();

        // =============================================
        // 2. تعريف المواد
        // =============================================
        $subjectsData = [
            // ===== رياضيات =====
            [
                'name' => 'رياضيات',
                'code' => 'MATH',
                'grade_code' => 'G1',
                'teacher_phone' => '01012345678',
            ],
            [
                'name' => 'رياضيات',
                'code' => 'MATH',
                'grade_code' => 'G2',
                'teacher_phone' => '01012345678',
            ],
            [
                'name' => 'رياضيات',
                'code' => 'MATH',
                'grade_code' => 'G3',
                'teacher_phone' => '01098765432',
            ],
            [
                'name' => 'رياضيات',
                'code' => 'MATH',
                'grade_code' => 'G4',
                'teacher_phone' => '01098765432',
            ],

            // ===== فيزياء =====
            [
                'name' => 'فيزياء',
                'code' => 'PHY',
                'grade_code' => 'G1',
                'teacher_phone' => '01011111111',
            ],
            [
                'name' => 'فيزياء',
                'code' => 'PHY',
                'grade_code' => 'G2',
                'teacher_phone' => '01011111111',
            ],
            [
                'name' => 'فيزياء',
                'code' => 'PHY',
                'grade_code' => 'G3',
                'teacher_phone' => '01011111111',
            ],

            // ===== إنجليزي =====
            [
                'name' => 'إنجليزي',
                'code' => 'ENG',
                'grade_code' => 'G1',
                'teacher_phone' => '01022222222',
            ],
            [
                'name' => 'إنجليزي',
                'code' => 'ENG',
                'grade_code' => 'G2',
                'teacher_phone' => '01022222222',
            ],
            [
                'name' => 'إنجليزي',
                'code' => 'ENG',
                'grade_code' => 'G3',
                'teacher_phone' => '01022222222',
            ],

            // ===== كيمياء =====
            [
                'name' => 'كيمياء',
                'code' => 'CHEM',
                'grade_code' => 'G2',
                'teacher_phone' => '01033333333',
            ],
            [
                'name' => 'كيمياء',
                'code' => 'CHEM',
                'grade_code' => 'G3',
                'teacher_phone' => '01033333333',
            ],

            // ===== أحياء =====
            [
                'name' => 'أحياء',
                'code' => 'BIO',
                'grade_code' => 'G2',
                'teacher_phone' => '01044444444',
            ],
            [
                'name' => 'أحياء',
                'code' => 'BIO',
                'grade_code' => 'G3',
                'teacher_phone' => '01044444444',
            ],

            // ===== تاريخ =====
            [
                'name' => 'تاريخ',
                'code' => 'HIST',
                'grade_code' => 'G1',
                'teacher_phone' => '01055555555',
            ],
            [
                'name' => 'تاريخ',
                'code' => 'HIST',
                'grade_code' => 'G2',
                'teacher_phone' => '01055555555',
            ],

            // ===== جغرافيا =====
            [
                'name' => 'جغرافيا',
                'code' => 'GEO',
                'grade_code' => 'G1',
                'teacher_phone' => '01066666666',
            ],
            [
                'name' => 'جغرافيا',
                'code' => 'GEO',
                'grade_code' => 'G2',
                'teacher_phone' => '01066666666',
            ],
        ];

        // =============================================
        // 3. إنشاء المواد وإضافتها للمدرسين
        // =============================================
        foreach ($subjectsData as $data) {
            // البحث عن المدرس
            $teacher = $teachers[$data['teacher_phone']] ?? $defaultTeacher;
            
            if (!$teacher) {
                $this->command->warn("⚠️ المدرس غير موجود للرقم: {$data['teacher_phone']}");
                continue;
            }

            // البحث عن الصف
            $grade = $grades[$data['grade_code']] ?? null;
            
            if (!$grade) {
                $this->command->warn("⚠️ الصف غير موجود للكود: {$data['grade_code']}");
                continue;
            }

            // البحث عن المادة (أو إنشاؤها)
            $subject = Subject::firstOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'description' => "مادة {$data['name']} - كود {$data['code']}",
                    'is_active' => true,
                ]
            );

            // =============================================
            // 4. توليد كود فريد للمادة + الصف + المدرس
            // =============================================
            $accessCode = $this->generateAccessCode($subject, $grade, $teacher);

            // =============================================
            // 5. إضافة المادة للمدرس في هذا الصف
            // =============================================
            
                $assignment = TeacherSubjectGrade::firstOrCreate(
                    [
                        'teacher_id' => $teacher->id,
                        'subject_id' => $subject->id,
                        'grade_id' => $grade->id,
                    ],
                    [
                        'access_code' => $accessCode,
                        'is_active' => true,
                    ]
                );

        }
    }

    // =============================================
    // دوال مساعدة
    // =============================================

    /**
     * توليد كود فريد للمادة
     */
    private function generateAccessCode($subject, $grade, $teacher): string
    {
        // صيغة الكود: MATH-G1-TCH1
        $code = $subject->code . '-' . $grade->code . '-TCH' . $teacher->id;

        // التأكد من عدم التكرار
        $exists = TeacherSubjectGrade::where('access_code', $code)->exists();
        
        if ($exists) {
            // لو مكرر، نضيف رقم عشوائي
            $code = $subject->code . '-' . $grade->code . '-TCH' . $teacher->id . '-' . rand(10, 99);
            
            // لو لسه مكرر، نستخدم UUID
            if (TeacherSubjectGrade::where('access_code', $code)->exists()) {
                $code = $subject->code . '-' . $grade->code . '-' . Str::random(8);
            }
        }

        return $code;
    }
}
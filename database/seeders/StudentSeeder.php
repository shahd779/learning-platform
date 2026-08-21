<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Package;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;
use App\Models\Payment;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        // =============================================
        // 1. جلب البيانات المطلوبة
        // =============================================
        $grades = Grade::all();
        $subjects = Subject::all();
        $teacherSubjectGrades = TeacherSubjectGrade::all();
        $packages = Package::all();


        // =============================================
        // 2. إنشاء الطلاب
        // =============================================
        $students = [];

        // طلاب عاديين
        $studentNames = [
            'أحمد محمد', 'سارة علي', 'محمد خالد', 'فاطمة حسن', 
            'يوسف سعيد', 'نورا محمود', 'عمر إبراهيم', 'ليلى أحمد',
            'كريم مصطفى', 'منى علي', 'حسن عبدالله', 'ريم سامي',
            'عادل محمود', 'هناء خالد', 'طارق محمد', 'أميرة علي',
            'مصطفى أحمد', 'سلمى حسن', 'خالد إبراهيم', 'دينا محمود',
        ];

        foreach ($studentNames as $index => $name) {
            $phone = '011' . str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
            
            $student = User::create([
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make('password123'),
                'role' => 'student',
                'image' => null,
                'is_active' => true,
            ]);

            $students[] = $student;
        }

  

        foreach ($students as $student) {
            // عدد الاشتراكات لكل طالب (1-3)
            $numSubscriptions = rand(1, 3);
            
            // اختيار روابط عشوائية
            $randomTsgs = $teacherSubjectGrades->random(min($numSubscriptions, $teacherSubjectGrades->count()));

            foreach ($randomTsgs as $tsg) {
                // اختيار باقة عشوائية
                $package = $packages->random();

                // تاريخ الاشتراك (من 0 إلى 60 يوم مضت)
                $subscribedAt = now()->subDays(rand(0, 60));
                
                // تاريخ الانتهاء (بناءً على مدة الباقة)
                $expiresAt = $subscribedAt->copy()->addDays($package->duration_days);

                // حالة الاشتراك
                $status = 'active';
                if ($expiresAt < now()) {
                    $status = 'expired';
                } 

                StudentSubscription::create([
                    'student_id' => $student->id,
                    'teacher_subject_grade_id' => $tsg->id,
                    'package_id' => $package->id,
                    'status' => $status,
                    'subscribed_at' => $subscribedAt,
                    'expires_at' => $expiresAt,
                ]);
            }
        }



        $rejectionReasons = [
            'صورة التحويل غير واضحة',
            'رقم العملية غير صحيح',
            'المبلغ غير مطابق',
            'تم التحويل لحساب خاطئ',
            'بيانات الطالب غير صحيحة',
        ];

        $admins = User::where('role', 'admin')->get();
        $admin = $admins->first();

        foreach ($students as $student) {
            // عدد المدفوعات لكل طالب (0-3)
            $numPayments = rand(0, 3);
            
            if ($numPayments > 0) {
                $studentTsgs = TeacherSubjectGrade::whereIn('id', 
                    StudentSubscription::where('student_id', $student->id)
                        ->pluck('teacher_subject_grade_id')
                )->get();

                if ($studentTsgs->isEmpty()) {
                    continue;
                }

                for ($i = 0; $i < $numPayments; $i++) {
                    $tsg = $studentTsgs->random();
                    

                    // تاريخ الدفع
                    $createdAt = now()->subDays(rand(0, 90));
                    
                    // حالة الدفع
                    $statuses = ['pending', 'approved', 'rejected'];
                    $status = $statuses[array_rand($statuses)];

                    // في StudentSeeder.php، في جزء إنشاء المدفوعات، أضف `subscription_id`:

$paymentData = [
    'student_id' => $student->id,
    'teacher_subject_grade_id' => $tsg->id,
    'subscription_id' => $subscription->id ?? null, // ✅ أضف السطر ده
    'from_phone' => '01' . rand(100000000, 999999999),
    'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . rand(100000, 999999),
    'transfer_image' => 'payments/transfer_' . rand(1, 10) . '.jpg',
    'status' => $status,
    'created_at' => $createdAt,
];

                    // لو الحالة approved أو rejected
                    if ($status !== 'pending') {
                        $paymentData['reviewed_by'] = $admin ? $admin->id : null;
                        $paymentData['reviewed_at'] = $createdAt->copy()->addHours(rand(1, 48));
                    }

                    // لو الحالة rejected
                    if ($status === 'rejected') {
                        $paymentData['rejection_reason'] = $rejectionReasons[array_rand($rejectionReasons)];
                    }

                    Payment::create($paymentData);
                }
            }
        }

 
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Payment;
use App\Models\User;
use App\Models\TeacherSubjectGrade;
use App\Models\StudentSubscription;
use Illuminate\Support\Str;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        // جلب الطلاب
        $students = User::where('role', 'student')->get();
        
        // جلب روابط المدرسين (teacher_subject_grade)
        $teacherSubjectGrades = TeacherSubjectGrade::all();

        // لو مفيش بيانات، نخرج
        if ($students->isEmpty() || $teacherSubjectGrades->isEmpty()) {
            $this->command->warn('⚠️ لا يوجد طلاب أو روابط مدرسين لإضافة مدفوعات');
            return;
        }

        // جلب أدمن للمراجعة
        $admins = User::where('role', 'admin')->get();
        $admin = $admins->first();

        $rejectionReasons = [
            'صورة التحويل غير واضحة',
            'رقم العملية غير صحيح',
            'المبلغ غير مطابق',
            'تم التحويل لحساب خاطئ',
            'بيانات الطالب غير صحيحة',
        ];

        // =============================================
        // جلب الاشتراكات الموجودة لربطها بالمدفوعات
        // =============================================
        $subscriptions = StudentSubscription::all();


        // مدفوعات من شهرين
        foreach (range(1, 8) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            // جلب اشتراك للربط
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null, // ✅ ربط بالاشتراك
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => ['pending', 'approved', 'rejected'][array_rand(['pending', 'approved', 'rejected'])],
                'rejection_reason' => null,
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now()->subMonths(2)->addDays(rand(1, 28)),
                'created_at' => now()->subMonths(2)->addDays(rand(1, 28)),
            ]);
        }

        // مدفوعات من 3 شهور
        foreach (range(1, 6) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => ['pending', 'approved', 'rejected'][array_rand(['pending', 'approved', 'rejected'])],
                'rejection_reason' => null,
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now()->subMonths(3)->addDays(rand(1, 28)),
                'created_at' => now()->subMonths(3)->addDays(rand(1, 28)),
            ]);
        }

        // مدفوعات من 4 شهور
        foreach (range(1, 5) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => ['pending', 'approved', 'rejected'][array_rand(['pending', 'approved', 'rejected'])],
                'rejection_reason' => $rejectionReasons[array_rand($rejectionReasons)],
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now()->subMonths(4)->addDays(rand(1, 28)),
                'created_at' => now()->subMonths(4)->addDays(rand(1, 28)),
            ]);
        }

        // مدفوعات من 5 شهور
        foreach (range(1, 4) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => ['pending', 'approved', 'rejected'][array_rand(['pending', 'approved', 'rejected'])],
                'rejection_reason' => $rejectionReasons[array_rand($rejectionReasons)],
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now()->subMonths(5)->addDays(rand(1, 28)),
                'created_at' => now()->subMonths(5)->addDays(rand(1, 28)),
            ]);
        }

        // مدفوعات من 6 شهور
        foreach (range(1, 3) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => ['pending', 'approved', 'rejected'][array_rand(['pending', 'approved', 'rejected'])],
                'rejection_reason' => $rejectionReasons[array_rand($rejectionReasons)],
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now()->subMonths(6)->addDays(rand(1, 28)),
                'created_at' => now()->subMonths(6)->addDays(rand(1, 28)),
            ]);
        }


        // مدفوعات بانتظار المراجعة (pending)
        foreach (range(1, 10) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => 'pending',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'created_at' => now()->subDays(rand(1, 25)),
            ]);
        }

        // مدفوعات تمت الموافقة عليها (approved)
        foreach (range(1, 15) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now()->subDays(rand(1, 20)),
                'created_at' => now()->subDays(rand(1, 25)),
            ]);
        }

        // مدفوعات مرفوضة (rejected)
        foreach (range(1, 5) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => 'rejected',
                'rejection_reason' => $rejectionReasons[array_rand($rejectionReasons)],
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now()->subDays(rand(1, 15)),
                'created_at' => now()->subDays(rand(1, 25)),
            ]);
        }


        foreach (range(1, 8) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => ['pending', 'approved', 'rejected'][array_rand(['pending', 'approved', 'rejected'])],
                'rejection_reason' => null,
                'reviewed_by' => $admin ? $admin->id : null,
                'reviewed_at' => now()->subDays(rand(1, 5)),
                'created_at' => now()->subDays(rand(1, 10)),
            ]);
        }


        foreach (range(1, 3) as $i) {
            $student = $students->random();
            $tsg = $teacherSubjectGrades->random();
            
            $subscription = $subscriptions->where('student_id', $student->id)
                ->where('teacher_subject_grade_id', $tsg->id)
                ->first();

            Payment::create([
                'student_id' => $student->id,
                'teacher_subject_grade_id' => $tsg->id,
                'subscription_id' => $subscription->id ?? null,
                'from_phone' => '01' . fake()->randomNumber(9, true),
                'transaction_id' => 'TXN-' . strtoupper(Str::random(8)) . '-' . fake()->randomNumber(6),
                'transfer_image' => 'payments/transfer_' . fake()->randomNumber(3) . '.jpg',
                'status' => 'pending',
                'rejection_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'created_at' => now(),
            ]);
        }
    }
}
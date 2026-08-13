<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // بعد التعديل
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
    $table->foreignId('teacher_subject_grade_id')->constrained('teacher_subject_grade')->onDelete('cascade');
    $table->decimal('amount', 10, 2);
    $table->string('from_phone'); // رقم التليفون المحول منه
    $table->string('to_phone'); // رقم التليفون المسجل عالمنصة
    $table->string('transaction_id')->unique(); // رقم العملية
    $table->string('transfer_image')->nullable(); // صورة التحويل
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->foreignId('reviewed_by')->nullable()->constrained('users');
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

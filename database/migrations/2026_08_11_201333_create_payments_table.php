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
    $table->foreign('student_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

    $table->foreign('teacher_subject_grade_id')
                  ->references('id')
                  ->on('teacher_subject_grade')
                  ->onDelete('set null');
    $table->string('from_phone');
    $table->string('transaction_id')->unique();
    $table->string('transfer_image')->nullable();
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->text('rejection_reason')->nullable(); 
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

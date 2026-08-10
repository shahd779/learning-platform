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
        
    Schema::create('assignments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('content_id')->constrained()->onDelete('cascade');
        $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
        $table->string('submission_file')->nullable(); // ملف التسليم
        $table->text('submission_text')->nullable(); // نص التسليم
        $table->integer('grade')->nullable(); // درجة الواجب
        $table->text('teacher_feedback')->nullable(); // ملاحظات المدرس
        $table->enum('status', ['pending', 'submitted', 'graded'])->default('pending');
        $table->timestamp('submitted_at')->nullable();
        $table->timestamp('graded_at')->nullable();
        $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};

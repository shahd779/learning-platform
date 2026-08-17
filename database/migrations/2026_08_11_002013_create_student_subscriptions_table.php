<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->nullable()->constrained('packages')->onDelete('set null');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('teacher_subject_grade_id')
                  ->constrained('teacher_subject_grade') 
                  ->onDelete('cascade');
            $table->enum('status', ['active', 'expired'])->default('active');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->unique(['student_id', 'teacher_subject_grade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subscriptions');
    }
};
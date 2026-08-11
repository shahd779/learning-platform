<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_subject_grade', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained()->onDelete('cascade');
            $table->foreignId('grade_id')->constrained()->onDelete('cascade');
            $table->string('access_code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // منع تكرار نفس المادة لنفس المدرس في نفس الصف
            $table->unique(['teacher_id', 'subject_id', 'grade_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_subject_grade');
    }
};
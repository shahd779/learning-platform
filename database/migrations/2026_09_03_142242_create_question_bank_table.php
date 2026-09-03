<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('grade_id')->constrained('grades')->onDelete('cascade');
            $table->enum('type', ['multiple_choice', 'true_false', 'essay']);
            $table->text('question');
            $table->integer('marks')->default(5);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->json('options')->nullable(); // للاختيار من متعدد
            $table->string('correct_answer')->nullable(); // للإجابة الصحيحة
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank');
    }
};
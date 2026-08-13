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
       Schema::create('exams', function (Blueprint $table) {
    $table->id();
    $table->foreignId('teacher_subject_grade_id')->constrained('teacher_subject_grade')->onDelete('cascade');
    $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
    $table->string('title');
    $table->text('description')->nullable();
    $table->json('questions')->nullable();
    $table->integer('total_marks');
    $table->timestamp('start_at')->nullable();
    $table->timestamp('end_at')->nullable();
    $table->enum('status', ['draft', 'published'])->default('draft');
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};

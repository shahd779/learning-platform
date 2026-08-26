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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_subject_grade_id')->constrained('teacher_subject_grade')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
    
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_type'); // pdf, docx, xlsx, zip, etc.
            $table->string('file_size'); // حجم الملف بالكيلوبايت
            $table->integer('downloads_count')->default(0);
    
    // إعدادات خاصة بالملف (تتجاوز الإعدادات العامة)
            $table->boolean('is_active')->default(true);
            $table->boolean('is_downloadable')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};

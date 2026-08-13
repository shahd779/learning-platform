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
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            
            // العلاقات الأساسية
            $table->foreignId('teacher_subject_grade_id')
                  ->constrained('teacher_subject_grade')
                  ->onDelete('cascade');
            
            $table->foreignId('teacher_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            
            $table->foreignId('subject_id')
                  ->constrained('subjects')
                  ->onDelete('cascade');
            
            // معلومات الفيديو
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_path')->nullable(); // لو رفع عالسيرفر
            $table->string('youtube_url')->nullable(); // لو يوتيوب
            $table->string('thumbnail')->nullable(); // صورة مصغرة
            $table->integer('duration')->nullable(); // المدة بالثواني
            
            // الترتيب
            $table->integer('order')->default(0);
            
            // الموافقة (الأدمن يراجع الفيديوهات فقط)
            $table->enum('status', ['pending', 'approved', 'rejected', 'revision'])
                  ->default('pending');
            
            $table->text('rejection_reason')->nullable(); // سبب الرفض أو طلب التعديل
            
            // الأدمن اللي راجع الفيديو
            $table->foreignId('reviewed_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            
            $table->timestamp('reviewed_at')->nullable();
            
            // المشاهدات
            $table->integer('views_count')->default(0);
            
            // هل الفيديو متاح للطلاب ولا لأ (بيتحدد بناءً على status approved)
            $table->boolean('is_published')->default(false);
            
            $table->timestamps();
            
            // Indexes للبحث السريع
            $table->index(['teacher_subject_grade_id', 'status']);
            $table->index(['teacher_id', 'status']);
            $table->index(['subject_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
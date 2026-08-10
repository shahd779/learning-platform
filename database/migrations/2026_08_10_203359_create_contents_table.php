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
        Schema::create('contents', function (Blueprint $table) {
        $table->id();
        $table->foreignId('subject_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->text('description')->nullable();
        $table->enum('type', ['video', 'assignment', 'exam']); // نوع المحتوى
        $table->string('video_url')->nullable(); // رابط الفيديو (YouTube أو Vimeo)
        $table->string('file_path')->nullable(); // لو في ملف PDF مرفق
        $table->json('questions')->nullable(); // للامتحانات (أسئلة وأجوبة)
        $table->integer('duration_minutes')->nullable(); // مدة الامتحان
        $table->integer('order')->default(0); // ترتيب المحتوى
        $table->boolean('is_published')->default(false);
        $table->timestamp('published_at')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contents');
    }
};

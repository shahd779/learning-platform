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
        Schema::create('video_progress', function (Blueprint $table) {
            $table->id();
        $table->foreignId('content_id')->constrained()->onDelete('cascade');
        $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
        $table->integer('progress_percentage')->default(0); // نسبة المشاهدة
        $table->integer('last_position')->default(0); // آخر موقف بالفيديو (بالثواني)
        $table->boolean('is_completed')->default(false);
        $table->timestamp('last_watched_at')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_progress');
    }
};

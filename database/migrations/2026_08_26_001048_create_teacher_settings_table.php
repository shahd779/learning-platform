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
        Schema::create('teacher_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
  
            $table->enum('videos_availability', ['always', 'limited'])->default('always');
            $table->integer('videos_availability_days')->nullable(); // لو limited
            $table->integer('videos_max_watch_count')->nullable(); // null = غير محدود
    
            $table->boolean('files_downloadable_by_default')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_settings');
    }
};

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
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
        $table->foreignId('content_id')->constrained()->onDelete('cascade');
        $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
        $table->json('answers')->nullable(); // إجابات الطالب
        $table->integer('score')->nullable(); // الدرجة
        $table->integer('total')->nullable(); // الدرجة الكلية
        $table->float('percentage')->nullable(); // النسبة المئوية
        $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};

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
    $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
    $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
    $table->json('answers')->nullable();
    $table->integer('score')->nullable();
    $table->integer('total')->nullable();
    $table->float('percentage')->nullable();
    $table->enum('status', ['pending', 'completed'])->default('pending');
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

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
        Schema::create('assignment_submissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
    $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
    $table->text('submission_text')->nullable();
    $table->string('file_path')->nullable();
    $table->enum('status', ['pending', 'submitted', 'graded'])->default('pending');
    $table->integer('grade')->nullable();
    $table->text('feedback')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};

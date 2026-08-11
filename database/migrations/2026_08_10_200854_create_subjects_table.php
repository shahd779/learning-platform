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
        Schema::create('subjects', function (Blueprint $table) {
       $table->id();
    $table->string('name'); // "رياضيات"
    $table->string('code')->unique(); // "MATH101" (المفتاح الأساسي)
    $table->text('description')->nullable();
    $table->foreignId('grade_id')->constrained()->onDelete('cascade'); // الصف
    $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade'); // المدرس
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    // منع تكرار نفس المادة لنفس المدرس في نفس الصف
    $table->unique(['name', 'grade_id', 'teacher_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};

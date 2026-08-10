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
        Schema::create('subscriptions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('subject_id')->constrained()->onDelete('cascade');
        $table->foreignId('package_id')->constrained()->onDelete('cascade');
        $table->string('access_code')->unique(); // كود دخول الطالب للمادة
        $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
        $table->date('start_date');
        $table->date('end_date');
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

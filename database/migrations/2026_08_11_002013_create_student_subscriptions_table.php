<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->nullable()->constrained('packages')->onDelete('set null');
            $table->foreign('student_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreign('teacher_subject_grade_id')
                  ->references('id')
                  ->on('teacher_subject_grade')
                  ->onDelete('set null');
            $table->enum('status', ['active', 'expired'])->default('active');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_free')->default(false);
            $table->timestamps();
            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subscriptions');
    }
};
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
            $table->foreignId('student_id')->nullable()
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');

            $table->foreignId('teacher_subject_grade_id')->nullable()
                  ->references('id')
                  ->on('teacher_subject_grade')
                  ->onDelete('set null');
            $table->enum('status', ['active', 'expired'])->default('active');
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_free')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->timestamp('banned_at')->nullable();
            $table->foreignId('banned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('ban_reason')->nullable();
            $table->timestamps();
            
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_subscriptions');
    }
};
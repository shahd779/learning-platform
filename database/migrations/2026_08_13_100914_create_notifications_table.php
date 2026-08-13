<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            
            // مين هيشوف الإشعار (المستخدم المستهدف)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // مين اللي عمل الإجراء (المدرس أو الأدمن)
            $table->foreignId('triggered_by_id')->constrained('users')->cascadeOnDelete();
            
            // نوع الإشعار (مثلاً: video_uploaded, video_approved, video_rejected)
            $table->string('type'); 
            
            // نص الإشعار (مثلاً: قام الأستاذ أحمد برفع فيديو جديد)
            $table->text('message');
            
            // بيانات إضافية (هتخزن فيها ID الفيديو عشان الرابط)
            $table->json('data')->nullable();
            
            // هل اتقرا ولا لأ
            $table->boolean('is_read')->default(false);
            
            $table->timestamps(); // created_at و updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
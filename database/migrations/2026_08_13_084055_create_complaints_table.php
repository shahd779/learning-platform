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
        Schema::create('complaints', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
    $table->enum('type', ['technical', 'behavior', 'content', 'payment', 'general'])->default('general');
    $table->string('subject');
    $table->text('description');
    $table->string('attachment')->nullable();
    $table->enum('status', ['pending', 'in_progress', 'resolved', 'closed'])->default('pending');
    $table->foreignId('assigned_to')->nullable()->constrained('users');
    $table->text('admin_response')->nullable();
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};

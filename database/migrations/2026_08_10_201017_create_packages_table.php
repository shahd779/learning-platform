// database/migrations/2026_08_12_000001_create_packages_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الباقة
            $table->text('description')->nullable(); // وصف الباقة
            $table->decimal('price', 8, 2); // سعر الباقة
            $table->integer('duration_days'); // مدة الباقة بالأيام
            $table->boolean('is_active')->default(true); // حالة الباقة
            $table->json('features')->nullable(); // مميزات الباقة
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
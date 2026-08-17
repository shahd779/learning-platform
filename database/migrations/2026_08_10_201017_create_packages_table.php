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
            $table->string('name'); 
            $table->decimal('price', 8, 2); 
            $table->integer('duration_days'); 
            $table->boolean('is_active')->default(true); 
            $table->json('features')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
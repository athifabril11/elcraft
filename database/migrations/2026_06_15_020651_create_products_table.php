<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('categories')
                  ->restrictOnDelete();
            $table->string('name', 200);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->enum('discount_type', ['none', 'percent', 'nominal'])
                  ->default('none');
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->date('discount_start')->nullable();
            $table->date('discount_end')->nullable();
            $table->integer('stock')->default(0);
            $table->integer('weight')->default(0)->comment('dalam gram');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
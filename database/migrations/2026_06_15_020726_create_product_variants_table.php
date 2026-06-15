<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->string('variant_name', 100);
            $table->string('variant_type', 50)->comment('Warna, Ukuran, dll');
            $table->decimal('additional_price', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->string('sku', 100)->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
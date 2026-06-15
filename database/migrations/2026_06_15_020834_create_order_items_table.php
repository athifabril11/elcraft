<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->cascadeOnDelete();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->restrictOnDelete();
            $table->foreignId('variant_id')
                  ->nullable()
                  ->constrained('product_variants')
                  ->nullOnDelete();
            $table->string('product_name', 200);
            $table->string('variant_name', 100)->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->integer('quantity')->default(1);
            $table->decimal('subtotal', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
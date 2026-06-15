<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->foreignId('order_item_id')
                  ->constrained('order_items')
                  ->cascadeOnDelete();
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('image', 255)->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
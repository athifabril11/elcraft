<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the return_items table.
     *
     * Each row links one OrderItem to one ReturnRequest with a quantity.
     * The compound unique index on (return_id, order_item_id) prevents the
     * same order item from appearing twice within a single return request.
     */
    public function up(): void
    {
        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')
                  ->constrained('returns')
                  ->cascadeOnDelete();
            $table->foreignId('order_item_id')
                  ->constrained('order_items')
                  ->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            // Prevents the same order item from being listed twice in one return
            $table->unique(['return_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items');
    }
};

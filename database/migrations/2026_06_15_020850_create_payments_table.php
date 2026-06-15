<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                  ->unique()
                  ->constrained('orders')
                  ->cascadeOnDelete();
            $table->string('midtrans_order_id', 100);
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_type', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])
                  ->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->text('snap_token')->nullable();
            $table->json('response_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
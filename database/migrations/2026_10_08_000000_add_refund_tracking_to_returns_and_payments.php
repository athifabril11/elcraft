<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add refund tracking columns to returns and extend payments.status
     * so refunded transactions can be recorded.
     *
     * returns.refund_status: null = belum direfund,
     *                         'api' = lewat Midtrans Refund API,
     *                         'manual' = ditandai admin (dashboard Midtrans / transfer bank)
     */
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->enum('refund_status', ['api', 'manual'])
                  ->nullable()
                  ->after('refund_amount');
            $table->timestamp('refunded_at')
                  ->nullable()
                  ->after('refund_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'success', 'failed', 'expired', 'refunded'])
                  ->default('pending')
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn(['refund_status', 'refunded_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])
                  ->default('pending')
                  ->change();
        });
    }
};

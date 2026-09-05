<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the returns table.
     *
     * One return request per order is enforced at the DB level via the
     * unique index on order_id. Status pipeline:
     *   diajukan → disetujui → diproses → selesai
     *              ↓
     *           ditolak
     */
    public function up(): void
    {
        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')
                  ->unique()
                  ->constrained('orders')
                  ->cascadeOnDelete();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->text('reason');
            $table->string('evidence_photo_path')->nullable();
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak', 'diproses', 'selesai'])
                  ->default('diajukan');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('returns');
    }
};

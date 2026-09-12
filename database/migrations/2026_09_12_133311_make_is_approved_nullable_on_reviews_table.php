<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make is_approved nullable so that NULL represents "pending" (awaiting moderation),
     * true = approved, false = rejected.
     *
     * No existing row values are modified — only the column definition changes.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('is_approved')->nullable()->default(null)->change();
        });
    }

    /**
     * Revert to NOT NULL DEFAULT false.
     *
     * NOTE: this will fail if any row has is_approved = NULL at rollback time.
     * That is acceptable — the down() migration is a best-effort revert.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('is_approved')->nullable(false)->default(false)->change();
        });
    }
};

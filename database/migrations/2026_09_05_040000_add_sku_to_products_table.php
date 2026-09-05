<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a nullable, unique SKU column to the products table.
     *
     * SKU lives at the product level (not per-variant).
     * Nullable so existing and future products can omit it.
     * MySQL unique indexes permit multiple NULL values, so many products
     * may have no SKU while still preventing duplicate non-null SKUs.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Positioned after 'slug' — both are human-readable product identifiers
            $table->string('sku', 100)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn('sku');
        });
    }
};

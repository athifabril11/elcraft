<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a unique index to the existing nullable `sku` column on product_variants.
     *
     * The column definition (nullable string) is unchanged — only a unique index
     * is added. MySQL unique indexes permit multiple NULL values, so variants
     * without a SKU are still allowed while preventing two non-null SKUs from
     * being identical across ANY variants (even across different products).
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropUnique(['sku']);
        });
    }
};

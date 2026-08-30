<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipping_discount_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('scope', ['province', 'city'])->comment('Whether rule applies to a province or a city');
            $table->unsignedInteger('scope_id')->comment('Province ID or City ID from RajaOngkir');
            $table->string('scope_name')->nullable()->comment('Human-readable province/city name for display');
            $table->enum('discount_type', ['free', 'fixed'])->comment('free = zero cost; fixed = subtract fixed amount');
            $table->unsignedInteger('discount_amount')->nullable()->comment('Amount to subtract in Rupiah (only for discount_type=fixed)');
            $table->string('description')->nullable()->comment('Admin note describing the rule');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'scope_id', 'is_active'], 'discount_rules_scope_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_discount_rules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();
            $table->string('label', 50)->default('Rumah');
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->string('province', 100);
            $table->string('city', 100);
            $table->string('district', 100);
            $table->string('postal_code', 10);
            $table->text('full_address');
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
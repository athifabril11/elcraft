<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Dapatkan semua user yang memiliki alamat
        $userIds = DB::table('addresses')->distinct()->pluck('user_id');

        foreach ($userIds as $userId) {
            $userAddresses = DB::table('addresses')
                ->where('user_id', $userId)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get();

            if ($userAddresses->isEmpty()) {
                continue;
            }

            // Pastikan tepat 1 alamat utama jika user memiliki alamat
            $hasDefault = false;
            $index = 1;

            foreach ($userAddresses as $addr) {
                $isDefault = false;

                if (!$hasDefault && $addr->is_default) {
                    $isDefault = true;
                    $hasDefault = true;
                } elseif (!$hasDefault && $index === 1) {
                    // Jika belum ada yang default sama sekali, jadikan yang pertama sebagai default
                    $isDefault = true;
                    $hasDefault = true;
                }

                // Perbaiki label jika 'Utama' tapi bukan default
                $label = $addr->label;
                if (!$isDefault && ($label === 'Utama' || empty($label))) {
                    $label = 'Alamat ' . $index;
                }

                DB::table('addresses')
                    ->where('id', $addr->id)
                    ->update([
                        'is_default' => $isDefault,
                        'label'      => $label,
                    ]);

                $index++;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};

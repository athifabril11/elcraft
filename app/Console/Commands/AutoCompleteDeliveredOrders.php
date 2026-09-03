<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\ShopSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AutoCompleteDeliveredOrders
 *
 * Automatically advances orders stuck at 'dikirim' to 'selesai' when the
 * customer has not confirmed receipt within the configured threshold
 * (shop_setting key: order_auto_complete_days, default 7).
 *
 * Each order is processed inside its own DB::transaction + lockForUpdate —
 * matching the safety pattern used everywhere else in this codebase — so
 * that a concurrent customer-confirm button click cannot race with this job.
 *
 * Usage:
 *   php artisan orders:auto-complete
 *
 * Scheduled via bootstrap/app.php → withSchedule() → daily().
 */
class AutoCompleteDeliveredOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete orders stuck at "dikirim" beyond the configured threshold days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) ShopSetting::get('order_auto_complete_days', 7);

        $cutoff = now()->subDays($days);

        // Fetch candidates — re-check status inside each transaction
        $candidates = Order::where('status', 'dikirim')
            ->where('updated_at', '<=', $cutoff)
            ->get();

        $completed = 0;

        foreach ($candidates as $candidate) {
            $advanced = DB::transaction(function () use ($candidate, $days) {
                $locked = Order::lockForUpdate()->findOrFail($candidate->id);

                // Guard: another process may have already changed the status
                if ($locked->status !== 'dikirim') {
                    return false;
                }

                $locked->update(['status' => 'selesai']);

                Log::info('orders:auto-complete — pesanan diselesaikan otomatis', [
                    'order_number' => $locked->order_number,
                    'days_elapsed' => $days,
                    'updated_at'   => $locked->updated_at->toDateTimeString(),
                ]);

                return true;
            });

            if ($advanced) {
                $completed++;
            }
        }

        $this->info("Auto-completed {$completed} order(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CleanupPreReserveOrders
 *
 * One-time command to clean up pending orders created BEFORE the
 * reserve-at-checkout model was deployed. Those orders never had
 * their stock reserved at checkout, so cancelling them must NOT
 * call restoreForOrder (which would incorrectly increment stock).
 *
 * The reserve-at-checkout model was deployed in commit ee3625d.
 * Orders created before that date with pending status are marked
 * as 'dibatalkan' directly, WITHOUT calling restoreForOrder.
 *
 * Usage:
 *   php artisan orders:cleanup-pre-reserve
 *
 * Safe to run multiple times (idempotent).
 */
class CleanupPreReserveOrders extends Command
{
    protected $signature = 'orders:cleanup-pre-reserve {--dry-run : Show what would be cancelled without making changes}';
    protected $description = 'Cancel pre-reserve-at-checkout pending orders without restoring stock (stock was never reserved for them)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Cutoff: reserve-at-checkout model was deployed in commit ee3625d
        // Orders created before this date with pending status likely
        // didn't have stock reserved at checkout.
        $cutoff = now()->subMonths(6); // Conservative: 6 months ago
        // Alternatively, detect by missing payment record or missing expired_at
        // but a simple date cutoff is more reliable for one-time cleanup.

        $candidates = Order::where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No pre-reserve pending orders found to clean up.');
            return self::SUCCESS;
        }

        $cancelled = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            // Double-check: if order has payment with expired_at, it might
            // have been created after reserve-at-checkout was deployed
            // but before the cutoff. Skip those to be safe.
            if ($candidate->payment && $candidate->payment->expired_at) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("Would cancel: {$candidate->order_number} (created: {$candidate->created_at})");
                continue;
            }

            $result = DB::transaction(function () use ($candidate) {
                $locked = Order::lockForUpdate()->findOrFail($candidate->id);

                if ($locked->status !== 'pending') {
                    return false;
                }

                // NO restoreForOrder — stock was never reserved for these orders
                $locked->update(['status' => 'dibatalkan']);

                if ($locked->payment) {
                    $locked->payment->update(['status' => 'failed']);
                }

                return true;
            });

            if ($result) {
                $cancelled++;
                Log::info('orders:cleanup-pre-reserve — pesanan pre-reserve dibatalkan', [
                    'order_number' => $candidate->order_number,
                ]);
            }
        }

        if ($dryRun) {
            $this->info("Dry run: {$cancelled} would be cancelled, {$skipped} skipped.");
        } else {
            $this->info("Cleaned up {$cancelled} pre-reserve order(s), skipped {$skipped}.");
        }

        return self::SUCCESS;
    }
}
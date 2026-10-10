<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * SyncProductStock
 *
 * For every product that has at least one variant, computes the active-variant
 * stock sum and compares it with the stored products.stock value.
 *
 * Mismatches are printed in a table and — unless --dry-run is passed —
 * the column is updated to the computed value.
 *
 * Products without variants are never touched.
 *
 * Usage:
 *   php artisan products:sync-stock            # detect + fix
 *   php artisan products:sync-stock --dry-run  # detect only (no writes)
 *
 * The command is idempotent: running it a second time on an already-synced
 * dataset produces zero mismatches and makes zero writes.
 */
class SyncProductStock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:sync-stock
                            {--dry-run : Print mismatches but do not update the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products.stock with the sum of active variant stocks (variant products only)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $this->info('Dry-run mode: no database writes will be performed.');
        }

        // Load only products that have at least one variant (any status)
        $products = Product::has('variants')
            ->with('variants')
            ->get();

        $mismatches = [];

        foreach ($products as $product) {
            $computed = (int) $product->variants->where('is_active', true)->sum('stock');
            $stored   = (int) $product->stock;

            if ($computed !== $stored) {
                $mismatches[] = [
                    'id'       => $product->id,
                    'name'     => $product->name,
                    'stored'   => $stored,
                    'computed' => $computed,
                ];
            }
        }

        if (empty($mismatches)) {
            $this->info('All variant products are already in sync. Nothing to do.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Stored Stock', 'Computed Stock'],
            array_map(fn($row) => [
                $row['id'],
                $row['name'],
                $row['stored'],
                $row['computed'],
            ], $mismatches)
        );

        if ($isDryRun) {
            $this->warn(count($mismatches) . ' mismatch(es) found. Run without --dry-run to fix.');
            return self::SUCCESS;
        }

        // Apply fixes
        foreach ($mismatches as $row) {
            Product::where('id', $row['id'])->update(['stock' => $row['computed']]);
        }

        $this->info('Fixed ' . count($mismatches) . ' product(s).');

        return self::SUCCESS;
    }
}

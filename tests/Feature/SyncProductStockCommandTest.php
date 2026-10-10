<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SyncProductStockCommandTest
 *
 * Tests for the `products:sync-stock` Artisan command.
 *
 * Covers:
 *  - --dry-run makes no DB changes
 *  - Normal run fixes mismatching products
 *  - Products without variants are never modified
 *  - Running twice changes nothing the second time (idempotency)
 */
class SyncProductStockCommandTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeCategory(): Category
    {
        return Category::create([
            'name'      => 'Cat ' . uniqid(),
            'slug'      => 'cat-' . uniqid(),
            'is_active' => true,
        ]);
    }

    private function makeProduct(int $stock): Product
    {
        $cat = $this->makeCategory();

        return Product::create([
            'category_id' => $cat->id,
            'name'        => 'Product ' . uniqid(),
            'slug'        => 'product-' . uniqid(),
            'price'       => 50000,
            'stock'       => $stock,
            'weight'      => 100,
            'is_active'   => true,
        ]);
    }

    private function makeVariant(Product $product, int $stock, bool $active = true): ProductVariant
    {
        return ProductVariant::create([
            'product_id'   => $product->id,
            'variant_name' => 'Varian ' . uniqid(),
            'variant_type' => 'Tipe',
            'stock'        => $stock,
            'is_active'    => $active,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * --dry-run must detect mismatches but must NOT update products.stock.
     */
    public function test_dry_run_changes_nothing(): void
    {
        $product = $this->makeProduct(80); // stale
        $this->makeVariant($product, 40, true);
        $this->makeVariant($product, 0, true);

        $this->artisan('products:sync-stock --dry-run')
            ->assertSuccessful();

        // Stock must still be 80 — no write happened
        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'stock' => 80,
        ]);
    }

    /**
     * A normal run must fix the stale column to match the active-variant sum.
     */
    public function test_normal_run_fixes_mismatches(): void
    {
        $product = $this->makeProduct(80); // stale
        $this->makeVariant($product, 40, true);  // active
        $this->makeVariant($product, 0, true);   // active

        $this->artisan('products:sync-stock')
            ->assertSuccessful();

        // Stock must now be 40 (sum of active variants)
        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'stock' => 40,
        ]);
    }

    /**
     * Products without variants must never be touched by the command.
     */
    public function test_products_without_variants_are_untouched(): void
    {
        $noVariantProduct = $this->makeProduct(55); // no variants

        $this->artisan('products:sync-stock')
            ->assertSuccessful();

        // Stock column must remain unchanged
        $this->assertDatabaseHas('products', [
            'id'    => $noVariantProduct->id,
            'stock' => 55,
        ]);
    }

    /**
     * Running the command twice on an already-synced dataset must change nothing.
     * This proves idempotency.
     */
    public function test_running_twice_changes_nothing_second_time(): void
    {
        $product = $this->makeProduct(80); // stale
        $this->makeVariant($product, 40, true);
        $this->makeVariant($product, 0, true);

        // First run — fixes the mismatch
        $this->artisan('products:sync-stock')->assertSuccessful();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 40]);

        // Second run — nothing to fix
        $this->artisan('products:sync-stock')->assertSuccessful();

        // Value unchanged
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 40]);
    }

    /**
     * Inactive variants must not contribute to the computed stock.
     */
    public function test_inactive_variants_excluded_from_sync(): void
    {
        $product = $this->makeProduct(99); // stale
        $this->makeVariant($product, 30, true);   // active
        $this->makeVariant($product, 100, false);  // inactive — excluded

        $this->artisan('products:sync-stock')->assertSuccessful();

        // Only the active variant's stock (30) counts
        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'stock' => 30,
        ]);
    }
}

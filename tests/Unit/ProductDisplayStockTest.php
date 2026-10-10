<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ProductDisplayStockTest
 *
 * Tests for the Product::display_stock accessor and
 * Product::recalculateStockFromVariants() method.
 */
class ProductDisplayStockTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeCategory(): Category
    {
        return Category::create([
            'name'      => 'Test Cat ' . uniqid(),
            'slug'      => 'test-cat-' . uniqid(),
            'is_active' => true,
        ]);
    }

    /**
     * Create a product with an explicit stock value (no variants by default).
     */
    private function makeProduct(int $stock = 10): Product
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

    /**
     * Attach a variant to the product.
     */
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

    // ── Tests: display_stock accessor ─────────────────────────────────────────

    /**
     * A product with no variants must return the products.stock column value.
     */
    public function test_no_variants_returns_stock_column(): void
    {
        $product = $this->makeProduct(25);

        $this->assertSame(25, $product->display_stock);
    }

    /**
     * A product with variants must return the SUM of active variant stock.
     */
    public function test_variants_returns_sum_of_active_variant_stock(): void
    {
        $product = $this->makeProduct(999); // stale parent value, should be ignored

        $this->makeVariant($product, 30, true);
        $this->makeVariant($product, 15, true);

        $product->load('variants');

        $this->assertSame(45, $product->display_stock);
    }

    /**
     * An inactive variant must NOT contribute to display_stock.
     */
    public function test_inactive_variant_is_excluded(): void
    {
        $product = $this->makeProduct(999);

        $this->makeVariant($product, 40, true);   // active
        $this->makeVariant($product, 20, false);  // inactive — excluded

        $product->load('variants');

        $this->assertSame(40, $product->display_stock);
    }

    /**
     * Stale parent column (80) with variants 40 active + 0 active returns 40.
     * This is the exact production scenario described in the task.
     */
    public function test_stale_parent_80_with_variants_40_and_0_returns_40(): void
    {
        $product = $this->makeProduct(80); // stale value

        $this->makeVariant($product, 40, true); // Set A
        $this->makeVariant($product, 0, true);  // Set B

        $product->load('variants');

        $this->assertSame(40, $product->display_stock);
    }

    /**
     * When ALL active variants have 0 stock, display_stock must be 0.
     */
    public function test_all_active_variants_at_zero_returns_zero(): void
    {
        $product = $this->makeProduct(999);

        $this->makeVariant($product, 0, true);
        $this->makeVariant($product, 0, true);

        $product->load('variants');

        $this->assertSame(0, $product->display_stock);
    }

    /**
     * display_stock falls back to querying when the variants relation is not loaded.
     */
    public function test_display_stock_works_without_preloaded_relation(): void
    {
        $product = $this->makeProduct(80);

        $this->makeVariant($product, 40, true);
        $this->makeVariant($product, 0, true);

        // Fresh instance — relation not loaded
        $fresh = Product::find($product->id);

        $this->assertSame(40, $fresh->display_stock);
    }

    // ── Tests: recalculateStockFromVariants() ─────────────────────────────────

    /**
     * recalculateStockFromVariants() must persist the correct value
     * and update the in-memory attribute; inactive variants are excluded.
     */
    public function test_recalculate_persists_correct_value_and_ignores_inactive(): void
    {
        $product = $this->makeProduct(80); // stale

        $this->makeVariant($product, 40, true);  // active
        $this->makeVariant($product, 10, false); // inactive — must be excluded

        $product->recalculateStockFromVariants();

        // In-memory attribute updated
        $this->assertSame(40, (int) $product->stock);

        // Persisted in DB
        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'stock' => 40,
        ]);
    }

    /**
     * Products without variants must not be touched by recalculateStockFromVariants().
     */
    public function test_products_without_variants_are_untouched(): void
    {
        $product = $this->makeProduct(55);

        $product->recalculateStockFromVariants(); // no-op

        // Stock column unchanged
        $this->assertDatabaseHas('products', [
            'id'    => $product->id,
            'stock' => 55,
        ]);

        // In-memory attribute unchanged
        $this->assertSame(55, (int) $product->stock);
    }
}

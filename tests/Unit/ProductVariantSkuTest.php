<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ProductVariantSkuTest
 *
 * Verifies the schema-level behaviour of the unique index added to the
 * nullable `sku` column on `product_variants`.
 *
 * Three invariants are tested (mirroring ProductSkuTest):
 *  1. A variant may have sku = null.
 *  2. Multiple variants (across different products) may simultaneously
 *     have sku = null without a uniqueness conflict.
 *  3. Two variants CANNOT share the same non-null SKU, even when they
 *     belong to entirely different products — the DB unique constraint
 *     raises a QueryException.
 */
class ProductVariantSkuTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeProduct(): Product
    {
        $category = Category::create([
            'name' => 'Test Cat ' . uniqid(),
            'slug' => 'test-cat-' . uniqid(),
        ]);

        return Product::create([
            'category_id' => $category->id,
            'name'        => 'Test Product ' . uniqid(),
            'slug'        => 'test-product-' . uniqid(),
            'price'       => 50000,
            'stock'       => 10,
            'weight'      => 200,
        ]);
    }

    private function makeVariant(Product $product, ?string $sku = null, string $suffix = ''): ProductVariant
    {
        return $product->variants()->create([
            'variant_name'     => 'Varian ' . uniqid() . $suffix,
            'variant_type'     => 'Warna',
            'additional_price' => 0,
            'stock'            => 5,
            'sku'              => $sku,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_variant_can_be_created_with_null_sku(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), null);

        $this->assertNull($variant->fresh()->sku);
    }

    public function test_multiple_variants_on_different_products_can_have_null_sku(): void
    {
        $v1 = $this->makeVariant($this->makeProduct(), null, '-a');
        $v2 = $this->makeVariant($this->makeProduct(), null, '-b');

        // Both rows exist with sku = null — no unique constraint violation
        $this->assertNull($v1->fresh()->sku);
        $this->assertNull($v2->fresh()->sku);
        $this->assertDatabaseCount('product_variants', 2);
    }

    public function test_variant_can_be_created_with_a_non_null_sku(): void
    {
        $variant = $this->makeVariant($this->makeProduct(), 'TEST-SKU-001');

        $this->assertSame('TEST-SKU-001', $variant->fresh()->sku);
    }

    public function test_two_variants_on_same_product_cannot_share_non_null_sku(): void
    {
        $this->expectException(QueryException::class);

        $product = $this->makeProduct();
        $this->makeVariant($product, 'DUPE-SKU', '-first');
        $this->makeVariant($product, 'DUPE-SKU', '-second'); // must throw
    }

    public function test_two_variants_on_different_products_cannot_share_non_null_sku(): void
    {
        $this->expectException(QueryException::class);

        $this->makeVariant($this->makeProduct(), 'CROSS-DUPE', '-a');
        $this->makeVariant($this->makeProduct(), 'CROSS-DUPE', '-b'); // must throw
    }
}

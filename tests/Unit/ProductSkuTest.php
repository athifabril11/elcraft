<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ProductSkuTest
 *
 * Verifies the schema-level behaviour of the nullable, unique `sku`
 * column added to the `products` table.
 *
 * Three invariants are tested:
 *  1. A product may omit SKU entirely (nullable).
 *  2. Multiple products may simultaneously have sku = null (unique index
 *     allows multiple NULLs in MySQL).
 *  3. Two products CANNOT share the same non-null SKU value — the DB
 *     unique constraint raises a QueryException.
 *
 * NOTE: No UI changes were made. SKU input will be added when the Admin
 * Produk CRUD module is built. This test is schema/model-level only.
 */
class ProductSkuTest extends TestCase
{
    use RefreshDatabase;

    // ── Helper ───────────────────────────────────────────────────────────────

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Test Category ' . uniqid(),
            'slug' => 'test-category-' . uniqid(),
        ]);
    }

    private function makeProduct(Category $category, ?string $sku = null, string $suffix = ''): Product
    {
        return Product::create([
            'category_id'   => $category->id,
            'name'          => 'Test Product ' . uniqid() . $suffix,
            'slug'          => 'test-product-' . uniqid() . $suffix,
            'sku'           => $sku,
            'price'         => 50000,
            'stock'         => 10,
            'weight'        => 200,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_product_can_be_created_with_null_sku(): void
    {
        $category = $this->makeCategory();
        $product  = $this->makeProduct($category, null);

        $this->assertNull($product->fresh()->sku);
    }

    public function test_multiple_products_can_have_null_sku_simultaneously(): void
    {
        $category = $this->makeCategory();

        $p1 = $this->makeProduct($category, null, '-a');
        $p2 = $this->makeProduct($category, null, '-b');

        // Both rows exist with sku = null — no unique constraint violation
        $this->assertNull($p1->fresh()->sku);
        $this->assertNull($p2->fresh()->sku);
        $this->assertDatabaseCount('products', 2);
    }

    public function test_product_can_be_created_with_a_sku(): void
    {
        $category = $this->makeCategory();
        $product  = $this->makeProduct($category, 'SKU-001');

        $this->assertSame('SKU-001', $product->fresh()->sku);
    }

    public function test_two_products_cannot_share_the_same_non_null_sku(): void
    {
        $this->expectException(QueryException::class);

        $category = $this->makeCategory();
        $this->makeProduct($category, 'SKU-DUPE', '-first');
        $this->makeProduct($category, 'SKU-DUPE', '-second'); // must throw
    }

    public function test_sku_is_in_fillable(): void
    {
        $product = new Product();

        $this->assertContains('sku', $product->getFillable());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ProductDetailStockDisplayTest
 *
 * Verifies that the product detail page renders the correct effective stock
 * (display_stock) and does NOT show the stale products.stock column value
 * when a product has variants.
 *
 * Also verifies that the "Gratis Ongkir" text has been removed from the
 * product info grid.
 */
class ProductDetailStockDisplayTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeActiveCategory(): Category
    {
        return Category::create([
            'name'      => 'Test Kategori',
            'slug'      => 'test-kategori-' . uniqid(),
            'is_active' => true,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * Stale parent stock = 80, variant A stock = 40 (active), variant B = 0 (active).
     * The page must render 40 (the effective sum) — not 80 (the stale column).
     * The "Stok Tersedia" badge must appear (effective stock > 0).
     */
    public function test_product_detail_shows_effective_stock_not_stale_column(): void
    {
        $category = $this->makeActiveCategory();

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Produk Stale Stock',
            'slug'        => 'produk-stale-stock',
            'price'       => 100000,
            'stock'       => 80, // stale value
            'weight'      => 50,
            'is_active'   => true,
        ]);

        ProductVariant::create([
            'product_id'   => $product->id,
            'variant_name' => 'Set A',
            'variant_type' => 'Set',
            'stock'        => 40,
            'is_active'    => true,
        ]);

        ProductVariant::create([
            'product_id'   => $product->id,
            'variant_name' => 'Set B',
            'variant_type' => 'Set',
            'stock'        => 0,
            'is_active'    => true,
        ]);

        $response = $this->get(route('products.show', $product->slug));

        $response->assertOk();

        $html = $response->getContent();

        // 1. The initialStock passed to Alpine must be 40 (not 80)
        //    It appears inside the productDetail(...) call as the third argument.
        $this->assertStringContainsString(
            ', 40,',
            $html,
            'The Alpine initialStock argument must be 40 (display_stock), not the stale 80.'
        );

        // 2. The stale value 80 must NOT appear in the x-data initialisation string
        //    Find the x-data div and assert 80 is not the third positional arg.
        //    We check the productDetail function call fragment.
        $this->assertStringNotContainsString(
            ', 80,',
            $html,
            'The stale stock value 80 must not be passed to the Alpine component.'
        );

        // 3. "Stok Tersedia" badge must appear (effective stock = 40 > 0)
        $response->assertSee('Stok Tersedia', false);

        // 4. "Habis" badge must NOT appear (we have 40 in stock)
        $this->assertStringNotContainsString(
            'Habis</span>',
            $html
        );
    }

    /**
     * When every active variant has 0 stock the page must show the "Habis" badge.
     */
    public function test_product_detail_shows_habis_when_all_active_variants_are_zero(): void
    {
        $category = $this->makeActiveCategory();

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Produk Habis',
            'slug'        => 'produk-habis-' . uniqid(),
            'price'       => 80000,
            'stock'       => 50, // stale — all active variants are 0
            'weight'      => 30,
            'is_active'   => true,
        ]);

        ProductVariant::create([
            'product_id'   => $product->id,
            'variant_name' => 'Warna Merah',
            'variant_type' => 'Warna',
            'stock'        => 0,
            'is_active'    => true,
        ]);

        ProductVariant::create([
            'product_id'   => $product->id,
            'variant_name' => 'Warna Biru',
            'variant_type' => 'Warna',
            'stock'        => 0,
            'is_active'    => true,
        ]);

        $response = $this->get(route('products.show', $product->slug));

        $response->assertOk();

        // "Habis" badge must appear (display_stock = 0)
        $response->assertSee('Habis', false);

        // "Stok Tersedia" badge must NOT appear
        $response->assertDontSee('Stok Tersedia', false);
    }

    /**
     * "Gratis Ongkir" must NOT appear anywhere on the product detail page.
     */
    public function test_product_detail_does_not_contain_gratis_ongkir(): void
    {
        $category = $this->makeActiveCategory();

        $product = Product::create([
            'category_id' => $category->id,
            'name'        => 'Produk Tanpa Ongkir',
            'slug'        => 'produk-tanpa-ongkir-' . uniqid(),
            'price'       => 60000,
            'stock'       => 10,
            'weight'      => 20,
            'is_active'   => true,
        ]);

        $response = $this->get(route('products.show', $product->slug));

        $response->assertOk();

        // The info grid entry for "Gratis Ongkir" must be gone
        $response->assertDontSee('Gratis Ongkir', false);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Tampilkan katalog produk kerajinan & aksesoris di panel admin.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $categoryFilter = $request->query('category');
        $stockFilter = $request->query('stock');
        $statusFilter = $request->query('status');

        $query = Product::with(['category', 'images', 'variants'])->latest();

        // 1. Search Query
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 2. Filter Kategori
        if ($categoryFilter) {
            $query->where(function ($q) use ($categoryFilter) {
                if (is_numeric($categoryFilter)) {
                    $q->where('category_id', $categoryFilter);
                } else {
                    $q->whereHas('category', function ($cq) use ($categoryFilter) {
                        $cq->where('slug', $categoryFilter);
                    });
                }
            });
        }

        // 3. Filter Stok
        if ($stockFilter === 'safe') {
            $query->where('stock', '>', 3);
        } elseif ($stockFilter === 'low') {
            $query->where('stock', '<=', 3)->where('stock', '>', 0);
        } elseif ($stockFilter === 'out') {
            $query->where('stock', '<=', 0);
        }

        // 4. Filter Status
        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $products = $query->paginate(15)->withQueryString();

        // Statistik Ringkas
        $totalProducts = Product::count();
        $safeStockCount = Product::where('stock', '>', 3)->count();
        $lowStockCount = Product::where('stock', '<=', 3)->where('stock', '>', 0)->count();
        $outOfStockCount = Product::where('stock', '<=', 0)->count();

        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return view('admin.products.index', compact(
            'products',
            'categories',
            'search',
            'categoryFilter',
            'stockFilter',
            'statusFilter',
            'totalProducts',
            'safeStockCount',
            'lowStockCount',
            'outOfStockCount'
        ));
    }

    /**
     * Simpan produk karya baru beserta varian dan foto produk.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'sku' => 'nullable|string|max:50|unique:products,sku',
            'price' => 'required|numeric|min:0',
            'weight' => 'required|integer|min:1',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'discount_type' => 'nullable|in:none,percent,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_start' => 'nullable|date',
            'discount_end' => 'nullable|date|after_or_equal:discount_start',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'primary_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variants' => 'nullable|array',
            'variants.*.variant_name' => 'required_with:variants|string|max:100',
            'variants.*.sku' => 'nullable|string|max:50',
            'variants.*.additional_price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',
            'variants.*.is_active' => 'nullable',
        ]);

        DB::transaction(function () use ($request, $validated) {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$counter}";
                $counter++;
            }

            $product = Product::create([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'slug' => $slug,
                'sku' => !empty($validated['sku']) ? strtoupper(trim($validated['sku'])) : null,
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'discount_type' => $validated['discount_type'] ?? 'none',
                'discount_value' => $validated['discount_value'] ?? 0,
                'discount_start' => $validated['discount_start'] ?? null,
                'discount_end' => $validated['discount_end'] ?? null,
                'stock' => $validated['stock'],
                'weight' => $validated['weight'] ?? 100,
                'is_featured' => $request->has('is_featured'),
                'is_active' => $request->has('is_active') ? true : false,
            ]);

            // 1. Simpan Foto Utama Produk
            if ($request->hasFile('primary_image')) {
                $path = $request->file('primary_image')->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $path,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            }

            // 2. Simpan Foto Tambahan Produk
            if ($request->hasFile('additional_images')) {
                foreach ($request->file('additional_images') as $idx => $file) {
                    $path = $file->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $path,
                        'is_primary' => false,
                        'sort_order' => $idx + 1,
                    ]);
                }
            }

            // 3. Simpan Varian Produk (Jika Ada)
            if (!empty($validated['variants'])) {
                $totalVariantStock = 0;
                $hasVariantStock = false;

                foreach ($validated['variants'] as $var) {
                    if (empty($var['variant_name'])) continue;

                    $vStock = isset($var['stock']) && $var['stock'] !== '' ? (int) $var['stock'] : 0;
                    $isActive = !array_key_exists('is_active', $var) ? true : (!empty($var['is_active']) && ($var['is_active'] == '1' || $var['is_active'] === true || $var['is_active'] === 'true' || $var['is_active'] === 'on'));

                    ProductVariant::create([
                        'product_id' => $product->id,
                        'variant_name' => $var['variant_name'],
                        'variant_type' => 'Opsi',
                        'additional_price' => $var['additional_price'] ?? 0,
                        'stock' => $vStock,
                        'sku' => !empty($var['sku']) ? strtoupper(trim($var['sku'])) : null,
                        'is_active' => $isActive,
                    ]);

                    $totalVariantStock += $vStock;
                    $hasVariantStock = true;
                }

                // Jika varian diisi, sinkronkan total stok produk dari varian aktif
                if ($hasVariantStock) {
                    $product->recalculateStockFromVariants();
                }
            }
        });

        return redirect()->route('admin.products.index')
            ->with('success', "Produk \"{$validated['name']}\" berhasil ditambahkan ke katalog.");
    }

    /**
     * Perbarui produk karya.
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'sku' => 'nullable|string|max:50|unique:products,sku,' . $product->id,
            'price' => 'required|numeric|min:0',
            'weight' => 'required|integer|min:1',
            'stock' => 'required|integer|min:0',
            'description' => 'nullable|string',
            'discount_type' => 'nullable|in:none,percent,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'discount_start' => 'nullable|date',
            'discount_end' => 'nullable|date|after_or_equal:discount_start',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'primary_image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'additional_images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|exists:product_variants,id',
            'variants.*.variant_name' => 'required_with:variants|string|max:100',
            'variants.*.sku' => 'nullable|string|max:50',
            'variants.*.additional_price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',
            'variants.*.is_active' => 'nullable',
        ]);

        DB::transaction(function () use ($request, $validated, $product) {
            $product->update([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'sku' => !empty($validated['sku']) ? strtoupper(trim($validated['sku'])) : null,
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'discount_type' => $validated['discount_type'] ?? 'none',
                'discount_value' => $validated['discount_value'] ?? 0,
                'discount_start' => $validated['discount_start'] ?? null,
                'discount_end' => $validated['discount_end'] ?? null,
                'stock' => $validated['stock'],
                'weight' => $validated['weight'] ?? 100,
                'is_featured' => $request->has('is_featured'),
                'is_active' => $request->has('is_active') ? true : false,
            ]);

            // 1. Update Foto Utama jika diunggah baru
            if ($request->hasFile('primary_image')) {
                // Nonaktifkan primary lama
                ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);

                $path = $request->file('primary_image')->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => $path,
                    'is_primary' => true,
                    'sort_order' => 0,
                ]);
            }

            // 2. Tambah Foto Produk Tambahan jika ada
            if ($request->hasFile('additional_images')) {
                $maxSort = ProductImage::where('product_id', $product->id)->max('sort_order') ?? 0;
                foreach ($request->file('additional_images') as $idx => $file) {
                    $path = $file->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $path,
                        'is_primary' => false,
                        'sort_order' => $maxSort + $idx + 1,
                    ]);
                }
            }

            // 3. Update Varian Produk
            if ($request->has('variants')) {
                $keptIds = [];
                $totalVarStock = 0;
                $hasVar = false;

                foreach ($request->input('variants', []) as $var) {
                    if (empty($var['variant_name'])) continue;

                    $vStock = isset($var['stock']) && $var['stock'] !== '' ? (int) $var['stock'] : 0;
                    $isActive = !array_key_exists('is_active', $var) ? true : (!empty($var['is_active']) && ($var['is_active'] == '1' || $var['is_active'] === true || $var['is_active'] === 'true' || $var['is_active'] === 'on'));

                    $varData = [
                        'variant_name' => $var['variant_name'],
                        'variant_type' => 'Opsi',
                        'additional_price' => $var['additional_price'] ?? 0,
                        'stock' => $vStock,
                        'sku' => !empty($var['sku']) ? strtoupper(trim($var['sku'])) : null,
                        'is_active' => $isActive,
                    ];

                    if (!empty($var['id'])) {
                        $existingVar = ProductVariant::where('product_id', $product->id)->find($var['id']);
                        if ($existingVar) {
                            $existingVar->update($varData);
                            $keptIds[] = $existingVar->id;
                        }
                    } else {
                        $newVar = ProductVariant::create(array_merge($varData, ['product_id' => $product->id]));
                        $keptIds[] = $newVar->id;
                    }

                    $totalVarStock += $vStock;
                    $hasVar = true;
                }

                // Hapus varian yang dihapus dari list
                ProductVariant::where('product_id', $product->id)
                    ->whereNotIn('id', $keptIds)
                    ->delete();

                if ($hasVar) {
                    $product->recalculateStockFromVariants();
                }
            }
        });

        return redirect()->route('admin.products.index')
            ->with('success', "Produk \"{$product->name}\" berhasil diperbarui.");
    }

    /**
     * Toggle status aktif/nonaktif varian produk (AJAX).
     */
    public function toggleVariant(Product $product, ProductVariant $variant): JsonResponse
    {
        if ($variant->product_id !== $product->id) {
            abort(404, 'Varian tidak ditemukan untuk produk ini.');
        }

        $variant->update(['is_active' => !$variant->is_active]);
        $product->recalculateStockFromVariants();

        return response()->json([
            'success' => true,
            'is_active' => (bool) $variant->is_active,
        ]);
    }

    /**
     * Toggle status aktif/nonaktif produk.
     */
    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => !$product->is_active]);
        $statusText = $product->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin.products.index')
            ->with('success', "Produk \"{$product->name}\" berhasil {$statusText}.");
    }

    /**
     * Hapus produk (cek riwayat pesanan terlebih dahulu).
     */
    public function destroy(Product $product): RedirectResponse
    {
        $orderCount = $product->orderItems()->count();

        if ($orderCount > 0) {
            // Jangan hapus produk yang ada riwayat ordernya, nonaktifkan saja untuk keamanan database
            $product->update(['is_active' => false]);
            return redirect()->route('admin.products.index')
                ->with('error', "Produk \"{$product->name}\" memiliki {$orderCount} riwayat transaksi pesanan sehingga dinonaktifkan (tidak dihapus permanen demi keutuhan data invoice).");
        }

        $name = $product->name;

        DB::transaction(function () use ($product) {
            // Hapus file gambar
            foreach ($product->images as $img) {
                if ($img->image_url && Storage::disk('public')->exists($img->image_url)) {
                    Storage::disk('public')->delete($img->image_url);
                }
                $img->delete();
            }

            $product->variants()->delete();
            $product->delete();
        });

        return redirect()->route('admin.products.index')
            ->with('success', "Produk \"{$name}\" berhasil dihapus.");
    }
}

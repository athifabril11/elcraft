<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use Illuminate\Validation\ValidationException;

/**
 * StockService — reserve-at-checkout stock model.
 *
 * Laravel-ecommerce-engine: stok DIKURANGI (di-reserve) di dalam transaksi
 * checkout dengan lockForUpdate, dan DIPULIHKAN di setiap jalur pembatalan.
 * Settlement webhook TIDAK menyentuh stok lagi — reservation sudah dibuat
 * saat pesanan dibuat, sehingga overselling dan stok negatif tidak mungkin
 * terjadi.
 */
class StockService
{
    /**
     * Reserve (decrement) stock untuk seluruh item keranjang.
     * Harus dipanggil di dalam DB::transaction().
     *
     * @throws ValidationException jika stok tidak mencukupi (transaksi rollback).
     */
    public function reserveForCart(Cart $cart): void
    {
        $variantProductIds = [];

        foreach ($cart->items as $item) {
            if ($item->variant_id) {
                $variant = ProductVariant::where('id', $item->variant_id)
                    ->lockForUpdate()
                    ->first();

                if (!$variant || !$variant->is_active || $variant->stock < $item->quantity) {
                    $available = $variant ? (int) $variant->stock : 0;
                    throw ValidationException::withMessages([
                        'stock' => 'Stok produk "' . $item->product->name . '" tidak mencukupi. Tersedia: ' . $available,
                    ]);
                }

                $variant->decrement('stock', $item->quantity);
                $variantProductIds[$item->product_id ?? $variant->product_id] = true;
            } else {
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product || !$product->is_active || $product->stock < $item->quantity) {
                    $available = $product ? (int) $product->stock : 0;
                    throw ValidationException::withMessages([
                        'stock' => 'Stok produk "' . $item->product->name . '" tidak mencukupi. Tersedia: ' . $available,
                    ]);
                }

                $product->decrement('stock', $item->quantity);
            }
        }

        $this->syncParentStock($variantProductIds);
    }

    /**
     * Pulihkan reservasi stok sebuah order (dipanggil saat pembatalan).
     * Idempoten secara struktural: hanya dipanggil tepat satu kali pada
     * transisi status -> 'dibatalkan' yang sudah dijaga lockForUpdate + recheck.
     * Harus dipanggil di dalam DB::transaction().
     */
    public function restoreForOrder(Order $order): void
    {
        $order->loadMissing('items');
        $variantProductIds = [];

        foreach ($order->items as $item) {
            if ($item->variant_id) {
                $variant = ProductVariant::where('id', $item->variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($variant) {
                    $variant->increment('stock', $item->quantity);
                    $variantProductIds[$item->product_id ?? $variant->product_id] = true;
                }
            } else {
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }
        }

        $this->syncParentStock($variantProductIds);
    }

    /**
     * Pulihkan stok untuk barang yang dikembalaskan lewat retur tukar_barang.
     * Mengembalikan kuantitas yang benar-benar diretur (bukan seluruh order).
     * Idempoten secara struktural: hanya dipanggil tepat satu kali pada
     * transisi diproses -> 'selesai' yang dijaga lockForUpdate + map status.
     * Harus dipanggil di dalam DB::transaction().
     */
    public function restoreForReturn(ReturnRequest $return): void
    {
        $return->loadMissing('items.orderItem');
        $variantProductIds = [];

        foreach ($return->items as $returnItem) {
            $orderItem = $returnItem->orderItem;

            if (!$orderItem) {
                continue;
            }

            if ($orderItem->variant_id) {
                $variant = ProductVariant::where('id', $orderItem->variant_id)
                    ->lockForUpdate()
                    ->first();

                if ($variant) {
                    $variant->increment('stock', $returnItem->quantity);
                    $variantProductIds[$orderItem->product_id ?? $variant->product_id] = true;
                }
            } else {
                $product = Product::where('id', $orderItem->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($product) {
                    $product->increment('stock', $returnItem->quantity);
                }
            }
        }

        $this->syncParentStock($variantProductIds);
    }

    /**
     * Sinkronkan agregat products.stock dengan jumlah stok variant aktif.
     */
    private function syncParentStock(array $productIds): void
    {
        foreach (array_unique($productIds) as $productId) {
            if ($productId) {
                $totalVariantStock = (int) ProductVariant::where('product_id', $productId)->sum('stock');
                Product::where('id', $productId)->update(['stock' => $totalVariantStock]);
            }
        }
    }
}

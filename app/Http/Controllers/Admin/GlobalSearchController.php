<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    /**
     * Handle live global search for the admin top search bar.
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (strlen($q) < 2) {
            return response()->json([
                'query' => $q,
                'results' => [
                    'menus' => [],
                    'orders' => [],
                    'products' => [],
                    'customers' => [],
                    'returns' => [],
                ],
                'total' => 0,
            ]);
        }

        // 1. Navigation / Menu shortcuts
        $menus = $this->searchMenus($q);

        // 2. Orders
        $orders = Order::with('user')
            ->where(function ($query) use ($q) {
                $query->where('order_number', 'like', "%{$q}%")
                      ->orWhereHas('user', function ($uq) use ($q) {
                          $uq->where('name', 'like', "%{$q}%")
                             ->orWhere('email', 'like', "%{$q}%");
                      });
            })
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($order) {
                $statusLabels = [
                    'pending' => 'Menunggu Bayar',
                    'paid' => 'Sudah Dibayar',
                    'diproses' => 'Diproses',
                    'dikirim' => 'Dikirim',
                    'selesai' => 'Selesai',
                    'dibatalkan' => 'Dibatalkan',
                ];
                return [
                    'id' => $order->id,
                    'title' => '#' . $order->order_number,
                    'subtitle' => ($order->user?->name ?? 'Guest') . ' • Rp ' . number_format($order->total_amount, 0, ',', '.'),
                    'badge' => $statusLabels[$order->status] ?? ucfirst($order->status),
                    'badge_color' => $order->status === 'selesai' ? 'emerald' : ($order->status === 'paid' ? 'indigo' : ($order->status === 'diproses' ? 'purple' : 'stone')),
                    'url' => route('admin.orders.index', ['highlight' => $order->id]),
                ];
            });

        // 3. Products
        $products = Product::with('category')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%")
                      ->orWhereHas('category', function ($cq) use ($q) {
                          $cq->where('name', 'like', "%{$q}%");
                      });
            })
            ->take(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->name,
                    'subtitle' => ($product->category?->name ?? 'Umum') . ($product->sku ? ' • ' . $product->sku : '') . ' • Rp ' . number_format($product->price, 0, ',', '.') . ' • Stok: ' . $product->stock,
                    'badge' => !$product->is_active ? 'Nonaktif' : ($product->stock <= 3 ? 'Stok Menipis' : 'Tersedia'),
                    'badge_color' => !$product->is_active ? 'stone' : ($product->stock <= 3 ? 'rose' : 'emerald'),
                    'url' => route('admin.products.index', ['q' => $product->name]),
                    'store_url' => route('products.show', $product->slug),
                ];
            });

        // 4. Customers
        $customers = User::where('role', 'customer')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%");
            })
            ->take(5)
            ->get()
            ->map(function ($cust) {
                return [
                    'id' => $cust->id,
                    'title' => $cust->name,
                    'subtitle' => $cust->email . ($cust->phone ? ' • ' . $cust->phone : ''),
                    'badge' => $cust->is_active ? 'Aktif' : 'Nonaktif',
                    'badge_color' => $cust->is_active ? 'emerald' : 'stone',
                    'url' => route('admin.customers.show', $cust->id),
                ];
            });

        // 5. Returns
        $returns = ReturnRequest::with(['order', 'user'])
            ->where(function ($query) use ($q) {
                $query->where('return_number', 'like', "%{$q}%")
                      ->orWhereHas('order', function ($oq) use ($q) {
                          $oq->where('order_number', 'like', "%{$q}%");
                      })
                      ->orWhereHas('user', function ($uq) use ($q) {
                          $uq->where('name', 'like', "%{$q}%");
                      });
            })
            ->take(5)
            ->get()
            ->map(function ($ret) {
                $retLabels = [
                    'diajukan' => 'Menunggu Review',
                    'disetujui' => 'Disetujui',
                    'diproses' => 'Diproses',
                    'selesai' => 'Selesai',
                    'ditolak' => 'Ditolak',
                ];
                return [
                    'id' => $ret->id,
                    'title' => '#' . $ret->return_number,
                    'subtitle' => ($ret->user?->name ?? 'Pelanggan') . ' • Ref: #' . ($ret->order?->order_number ?? '-'),
                    'badge' => $retLabels[$ret->status] ?? ucfirst($ret->status),
                    'badge_color' => $ret->status === 'diajukan' ? 'rose' : ($ret->status === 'selesai' ? 'emerald' : 'amber'),
                    'url' => route('admin.returns.show', $ret->id),
                ];
            });

        $totalCount = count($menus) + $orders->count() + $products->count() + $customers->count() + $returns->count();

        return response()->json([
            'query' => $q,
            'results' => [
                'menus' => $menus,
                'orders' => $orders,
                'products' => $products,
                'customers' => $customers,
                'returns' => $returns,
            ],
            'total' => $totalCount,
        ]);
    }

    /**
     * Search navigation / menu shortcuts.
     */
    private function searchMenus(string $q): array
    {
        $qLower = strtolower($q);
        $allMenus = [
            [
                'title' => 'Dashboard Utama',
                'keywords' => ['dashboard', 'utama', 'home', 'ringkasan', 'statistik', 'omzet'],
                'url' => route('admin.dashboard'),
                'icon' => 'grid_view',
            ],
            [
                'title' => 'Manajemen Pesanan',
                'keywords' => ['pesanan', 'order', 'orders', 'transaksi', 'invoice', 'pembayaran'],
                'url' => route('admin.orders.index'),
                'icon' => 'shopping_bag',
            ],
            [
                'title' => 'Retur & Pengembalian',
                'keywords' => ['retur', 'refund', 'kembali', 'komplain', 'rusak', 'return'],
                'url' => route('admin.returns.index'),
                'icon' => 'assignment_return',
            ],
            [
                'title' => 'Laporan Penjualan & Analitik',
                'keywords' => ['laporan', 'report', 'analitik', 'grafik', 'export', 'keuangan'],
                'url' => route('admin.reports.index'),
                'icon' => 'bar_chart',
            ],
            [
                'title' => 'Manajemen Pelanggan',
                'keywords' => ['pelanggan', 'customer', 'member', 'user', 'pengguna'],
                'url' => route('admin.customers.index'),
                'icon' => 'group',
            ],
            [
                'title' => 'Kelola Voucher Diskon',
                'keywords' => ['voucher', 'diskon', 'kupon', 'promo', 'potongan'],
                'url' => route('admin.vouchers.index'),
                'icon' => 'confirmation_number',
            ],
            [
                'title' => 'Banner Promosi Hero',
                'keywords' => ['banner', 'promo', 'slider', 'gambar', 'iklan'],
                'url' => route('admin.banners.index'),
                'icon' => 'view_carousel',
            ],
            [
                'title' => 'Pengaturan Asal Pengiriman',
                'keywords' => ['asal', 'shipping', 'pengiriman', 'kota', 'ekspedisi', 'gudang'],
                'url' => route('admin.settings.shipping.index'),
                'icon' => 'location_on',
            ],
            [
                'title' => 'Pengaturan Diskon Ongkir',
                'keywords' => ['diskon ongkir', 'gratis ongkir', 'potongan kirim', 'subsidi'],
                'url' => route('admin.settings.shipping-discounts.index'),
                'icon' => 'local_shipping',
            ],
            [
                'title' => 'Pengaturan Kurir Ekspedisi',
                'keywords' => ['kurir', 'jne', 'tiki', 'pos', 'jnt', 'sicepat'],
                'url' => route('admin.settings.couriers.index'),
                'icon' => 'local_shipping',
            ],
            [
                'title' => 'Auto-Selesai Pesanan',
                'keywords' => ['auto', 'selesai', 'otomatis', 'timer', 'cron'],
                'url' => route('admin.settings.order-auto-complete.index'),
                'icon' => 'timer',
            ],
            [
                'title' => 'Pengaturan Nomor WhatsApp',
                'keywords' => ['whatsapp', 'wa', 'nomor', 'kontak', 'hubungi', 'footer', 'chat'],
                'url' => route('admin.settings.whatsapp.index'),
                'icon' => 'chat',
            ],
            [
                'title' => 'Katalog Produk Publik',
                'keywords' => ['produk', 'product', 'katalog', 'stok', 'aksesoris', 'kalung', 'gelang'],
                'url' => route('products.index'),
                'icon' => 'inventory_2',
            ],
        ];

        $matched = [];
        foreach ($allMenus as $menu) {
            foreach ($menu['keywords'] as $kw) {
                if (str_contains($kw, $qLower) || str_contains($qLower, $kw)) {
                    $matched[] = [
                        'title' => $menu['title'],
                        'subtitle' => 'Menu Navigasi Admin',
                        'badge' => 'Menu',
                        'badge_color' => 'brand',
                        'url' => $menu['url'],
                    ];
                    break;
                }
            }
        }

        return array_slice($matched, 0, 3);
    }
}

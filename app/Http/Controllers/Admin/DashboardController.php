<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\Review;
use App\Models\ShippingDiscountRule;
use App\Models\ShopSetting;
use App\Models\User;
use App\Traits\HasDateRangeFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * DashboardController — Menampilkan ringkasan metrik riil toko el Craft.
 */
class DashboardController extends Controller
{
    use HasDateRangeFilter;

    public function index(Request $request)
    {
        [$startDate, $endDate, $period] = $this->resolveDateRange($request, 'this_month');

        // 1. Revenue & Transactions
        $totalRevenue = (float) Order::where('status', 'selesai')->sum('total_amount');
        $thisMonthRevenue = (float) Order::where('status', 'selesai')
            ->whereMonth('updated_at', Carbon::now()->month)
            ->whereYear('updated_at', Carbon::now()->year)
            ->sum('total_amount');
        $completedOrdersCount = Order::where('status', 'selesai')->count();

        // 2. Orders Needing Attention (paid & diproses)
        $paidOrdersCount = Order::where('status', 'paid')->count();
        $processingOrdersCount = Order::where('status', 'diproses')->count();
        $ordersNeedingAttention = $paidOrdersCount + $processingOrdersCount;
        $capturedStuckCount = Order::where('status', 'dibatalkan')
            ->whereHas('payment', fn($q) => $q->where('status', 'success'))
            ->count();

        // 3. Returns Needing Attention (diajukan)
        $pendingReturnsCount = ReturnRequest::where('status', 'diajukan')->count();
        $inProgressReturnsCount = ReturnRequest::whereIn('status', ['disetujui', 'diproses'])->count();

        // 4. Critical Stock (<= 3 pcs) — Variant-aware ranking for Hero Card 3
        $nonVariantLowStock = Product::where('is_active', true)
            ->doesntHave('variants')
            ->where('stock', '<=', 3)
            ->get(['id', 'name', 'stock'])
            ->map(fn($p) => [
                'name'         => $p->name,
                'stock'        => (int) $p->stock,
                'product_id'   => $p->id,
                'variant_name' => null,
            ]);

        $variantLowStock = ProductVariant::where('is_active', true)
            ->where('stock', '<=', 3)
            ->whereHas('product', fn($q) => $q->where('is_active', true))
            ->with('product:id,name')
            ->get()
            ->map(fn($v) => [
                'name'         => $v->product?->name ?? 'Produk',
                'stock'        => (int) $v->stock,
                'product_id'   => $v->product_id,
                'variant_name' => $v->variant_name,
            ]);

        $allCriticalItems = $nonVariantLowStock->concat($variantLowStock)->sortBy('stock')->values();
        $lowStockCriticalCount = $allCriticalItems->count();
        $lowStockRanking = $allCriticalItems->take(5);

        $lowStockCount = Product::where('stock', '<=', 3)->where('is_active', true)->count();
        $lowStockProducts = Product::with('category')
            ->where('stock', '<=', 3)
            ->where('is_active', true)
            ->orderBy('stock', 'asc')
            ->take(5)
            ->get();
        $totalActiveProducts = Product::where('is_active', true)->count();

        // 5. Customers
        $totalCustomers = User::where('role', 'customer')->count();
        $newCustomersThisWeek = User::where('role', 'customer')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // 6. Order Status Distribution Breakdown
        $orderCountsByStatus = [
            'pending'    => Order::where('status', 'pending')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'paid'       => Order::where('status', 'paid')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'diproses'   => Order::where('status', 'diproses')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'dikirim'    => Order::where('status', 'dikirim')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'selesai'    => Order::where('status', 'selesai')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'dibatalkan' => Order::where('status', 'dibatalkan')->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];
        $totalOrders = array_sum($orderCountsByStatus);

        // 7. Store / Logistics Settings
        $originCity = ShopSetting::get('shipping_origin_city_name', 'Jember');
        $originCityId = ShopSetting::get('shipping_origin_city_id', '160');
        $activeCouriersCount = Courier::where('is_active', true)->count();
        $totalCouriersCount = Courier::count();
        $activeDiscountRulesCount = ShippingDiscountRule::where('is_active', true)->count();
        $autoCompleteDays = ShopSetting::get('order_auto_complete_days', '3');

        // 8. Recent Orders
        $recentOrders = Order::with(['user', 'items'])
            ->latest()
            ->take(5)
            ->get();

        // 9. Today's Revenue & Transactions
        $todayRevenue = (float) Order::where('status', 'selesai')
            ->whereDate('updated_at', Carbon::today())
            ->sum('total_amount');
        $todayTransactionCount = Order::where('status', 'selesai')
            ->whereDate('updated_at', Carbon::today())
            ->count();

        // 10. Top Selling Products (last 30 days by quantity sold)
        $topProducts = \App\Models\OrderItem::selectRaw(
                'product_id, product_name, SUM(quantity) as total_sold, SUM(quantity * price) as total_revenue'
            )
            ->whereHas('order', fn($q) => $q->where('status', 'selesai')
                ->where('created_at', '>=', Carbon::now()->subDays(30)))
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        // 11. Recent customers with purchase status
        $recentCustomersFull = User::where('role', 'customer')
            ->withCount(['orders as has_ordered' => fn($q) => $q->where('status', 'selesai')])
            ->latest()
            ->take(5)
            ->get();

        // 12. Hero sparkline data (fixed to last 7 days revenue for real-time indicator)
        $heroSparkline = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $heroSparkline[] = [
                'label' => $date->isoFormat('ddd'),
                'revenue' => (float) Order::where('status', 'selesai')
                    ->whereDate('updated_at', $date)
                    ->sum('total_amount'),
            ];
        }

        // 13. Range-aware chart data (filtered by selected period)
        $daySpan = $startDate->diffInDays($endDate) + 1;

        if ($daySpan <= 31) {
            // Bucket by day
            $chartRevenue = [];
            for ($d = $startDate->copy(); $d->lte($endDate); $d->addDay()) {
                $chartRevenue[] = [
                    'label' => $d->isoFormat('D/M'),
                    'revenue' => (float) Order::where('status', 'selesai')
                        ->whereDate('updated_at', $d->format('Y-m-d'))
                        ->sum('total_amount'),
                ];
            }
        } else {
            // Bucket by week for longer ranges (e.g. "Semua")
            $chartRevenue = [];
            $weekStart = $startDate->copy()->startOfWeek();
            while ($weekStart->lte($endDate)) {
                $weekEnd = $weekStart->copy()->endOfWeek();
                $chartRevenue[] = [
                    'label' => $weekStart->isoFormat('D/M'),
                    'revenue' => (float) Order::where('status', 'selesai')
                        ->whereBetween('updated_at', [$weekStart, $weekEnd->min($endDate)])
                        ->sum('total_amount'),
                ];
                $weekStart->addWeek();
            }
        }

        // 14. Customer Reviews & Ratings
        $totalReviewsCount = Review::count();
        $averageRating = (float) (Review::avg('rating') ?: 5.0);
        $positiveReviewsCount = Review::where('rating', '>=', 4)->count();
        $satisfactionRate = $totalReviewsCount > 0 ? round(($positiveReviewsCount / $totalReviewsCount) * 100) : 100;
        $recentReviews = Review::with(['user', 'product'])
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalRevenue',
            'thisMonthRevenue',
            'completedOrdersCount',
            'paidOrdersCount',
            'processingOrdersCount',
            'ordersNeedingAttention',
            'pendingReturnsCount',
            'inProgressReturnsCount',
            'lowStockCount',
            'lowStockProducts',
            'lowStockRanking',
            'lowStockCriticalCount',
            'totalActiveProducts',
            'totalCustomers',
            'newCustomersThisWeek',
            'orderCountsByStatus',
            'totalOrders',
            'originCity',
            'originCityId',
            'activeCouriersCount',
            'totalCouriersCount',
            'activeDiscountRulesCount',
            'autoCompleteDays',
            'recentOrders',
            'todayRevenue',
            'todayTransactionCount',
            'topProducts',
            'recentCustomersFull',
            'heroSparkline',
            'chartRevenue',
            'period',
            'startDate',
            'endDate',
            'totalReviewsCount',
            'averageRating',
            'satisfactionRate',
            'recentReviews',
            'capturedStuckCount'
        ));
    }
}

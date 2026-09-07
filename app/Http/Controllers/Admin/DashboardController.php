<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ShippingDiscountRule;
use App\Models\ShopSetting;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * DashboardController — Menampilkan ringkasan metrik riil toko el Craft.
 */
class DashboardController extends Controller
{
    public function index()
    {
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

        // 3. Returns Needing Attention (diajukan)
        $pendingReturnsCount = ReturnRequest::where('status', 'diajukan')->count();
        $inProgressReturnsCount = ReturnRequest::whereIn('status', ['disetujui', 'diproses'])->count();

        // 4. Critical Stock (<= 3 pcs)
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
        $recentCustomers = User::where('role', 'customer')
            ->latest()
            ->take(3)
            ->get();

        // 6. Order Status Distribution Breakdown
        $orderCountsByStatus = [
            'pending'    => Order::where('status', 'pending')->count(),
            'paid'       => Order::where('status', 'paid')->count(),
            'diproses'   => Order::where('status', 'diproses')->count(),
            'dikirim'    => Order::where('status', 'dikirim')->count(),
            'selesai'    => Order::where('status', 'selesai')->count(),
            'dibatalkan' => Order::where('status', 'dibatalkan')->count(),
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

        // 12. Weekly chart data (last 7 days revenue)
        $weeklyRevenue = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $weeklyRevenue[] = [
                'label' => $date->isoFormat('ddd'),
                'revenue' => (float) Order::where('status', 'selesai')
                    ->whereDate('updated_at', $date)
                    ->sum('total_amount'),
            ];
        }

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
            'totalActiveProducts',
            'totalCustomers',
            'newCustomersThisWeek',
            'recentCustomers',
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
            'weeklyRevenue'
        ));
    }
}

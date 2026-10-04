<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Traits\HasDateRangeFilter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use HasDateRangeFilter;

    /**
     * Tampilkan halaman Laporan & Analitik Penjualan el Craft.
     */
    public function index(Request $request)
    {
        [$startDate, $endDate, $period] = $this->resolveDateRange($request);

        // Status pesanan yang dianggap telah menghasilkan pembayaran valid
        $paidStatuses = ['paid', 'diproses', 'dikirim', 'selesai'];

        // Base query pesanan dalam rentang tanggal
        $ordersBase = Order::whereBetween('created_at', [$startDate, $endDate]);
        $paidOrdersQuery = (clone $ordersBase)->whereIn('status', $paidStatuses);

        // 1. Ringkasan Finansial
        $grossSales = (float) (clone $paidOrdersQuery)->sum('total_amount');
        $voucherDiscounts = (float) (clone $paidOrdersQuery)->sum('voucher_discount');
        $shippingCosts = (float) (clone $paidOrdersQuery)->sum('shipping_cost');
        
        // Total refund dari retur yang disetujui / diproses / selesai
        $totalRefunds = (float) ReturnRequest::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['disetujui', 'diproses', 'selesai'])
            ->sum('refund_amount');

        $netSales = max(0, $grossSales - $totalRefunds);
        $totalPaidOrders = (clone $paidOrdersQuery)->count();

        // Total unit produk yang terjual
        $totalUnitsSold = (int) OrderItem::whereHas('order', function ($q) use ($startDate, $endDate, $paidStatuses) {
            $q->whereBetween('created_at', [$startDate, $endDate])
              ->whereIn('status', $paidStatuses);
        })->sum('quantity');

        // 2. Produk Terlaris (Top Selling Products)
        $topProducts = OrderItem::select(
                'product_id',
                'product_name',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(subtotal) as total_sales')
            )
            ->whereHas('order', function ($q) use ($startDate, $endDate, $paidStatuses) {
                $q->whereBetween('created_at', [$startDate, $endDate])
                  ->whereIn('status', $paidStatuses);
            })
            ->with(['product.images', 'product.category'])
            ->groupBy('product_id', 'product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // 3. Breakdown Penjualan per Kategori
        $categorySales = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->whereIn('orders.status', $paidStatuses)
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(order_items.subtotal) as total_revenue'),
                DB::raw('SUM(order_items.quantity) as total_qty')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_revenue')
            ->get();

        // 4. Tabel Transaksi Terinci (dengan pagination)
        $statusFilter = $request->query('status');
        $transactionsQuery = Order::with(['user', 'payment', 'items'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($statusFilter && $statusFilter !== 'all') {
            $transactionsQuery->where('status', $statusFilter);
        }

        $transactions = $transactionsQuery->latest()->paginate(10)->withQueryString();

        return view('admin.reports.index', compact(
            'period',
            'startDate',
            'endDate',
            'grossSales',
            'netSales',
            'voucherDiscounts',
            'shippingCosts',
            'totalRefunds',
            'totalPaidOrders',
            'totalUnitsSold',
            'topProducts',
            'categorySales',
            'transactions',
            'statusFilter'
        ));
    }

    /**
     * Export data transaksi ke format CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        [$startDate, $endDate, $period] = $this->resolveDateRange($request);
        $statusFilter = $request->query('status');

        $query = Order::with(['user', 'payment', 'items'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $filename = sprintf('laporan-transaksi-elcraft-%s-sd-%s.csv', $startDate->format('Ymd'), $endDate->format('Ymd'));

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM untuk kompatibilitas Microsoft Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header kolom
            fputcsv($handle, [
                'No. Pesanan',
                'Tanggal',
                'Nama Pelanggan',
                'Email Pelanggan',
                'Status Pesanan',
                'Metode Pembayaran',
                'Kurir',
                'Jumlah Item',
                'Subtotal (Rp)',
                'Diskon Voucher (Rp)',
                'Ongkir (Rp)',
                'Total Akhir (Rp)',
            ]);

            $query->chunk(100, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    $totalItems = $order->items->sum('quantity');
                    $paymentMethod = $order->payment?->payment_method ?? '-';

                    fputcsv($handle, [
                        $order->order_number,
                        $order->created_at->format('Y-m-d H:i:s'),
                        $order->user?->name ?? 'Guest/Dihapus',
                        $order->user?->email ?? '-',
                        strtoupper($order->status),
                        $paymentMethod,
                        strtoupper($order->courier ?? '-'),
                        $totalItems,
                        $order->subtotal,
                        $order->voucher_discount,
                        $order->shipping_cost,
                        $order->total_amount,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}


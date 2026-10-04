<?php

namespace App\Traits;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

trait HasDateRangeFilter
{
    /**
     * Hitung start_date dan end_date berdasarkan pilihan filter.
     */
    public function resolveDateRange(Request $request, string $defaultPeriod = 'this_month'): array
    {
        $period = $request->query('period', $defaultPeriod);

        switch ($period) {
            case 'today':
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
                break;

            case '7days':
                $startDate = Carbon::now()->subDays(6)->startOfDay();
                $endDate = Carbon::now()->endOfDay();
                break;

            case '30days':
                $startDate = Carbon::now()->subDays(29)->startOfDay();
                $endDate = Carbon::now()->endOfDay();
                break;

            case 'all':
                $startDate = \App\Models\Order::min('created_at') 
                    ? Carbon::parse(\App\Models\Order::min('created_at'))->startOfDay() 
                    : Carbon::now()->subYear();
                $endDate = Carbon::now()->endOfDay();
                break;

            case 'custom':
                $startInput = $request->query('start_date');
                $endInput = $request->query('end_date');

                $startDate = $startInput ? Carbon::parse($startInput)->startOfDay() : Carbon::now()->startOfMonth();
                $endDate = $endInput ? Carbon::parse($endInput)->endOfDay() : Carbon::now()->endOfDay();
                break;

            case 'this_month':
            default:
                $period = 'this_month';
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->endOfDay();
                break;
        }

        return [$startDate, $endDate, $period];
    }
}

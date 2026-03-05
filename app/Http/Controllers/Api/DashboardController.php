<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function stats()
    {
        $today = now()->startOfDay();
        
        $todaySales = Sale::whereBetween('created_at', [
            $today,
            $today->copy()->endOfDay(),
        ])->get();

        $totalRevenue = $todaySales->sum('total');
        $totalTransactions = $todaySales->count();
        $totalItems = $todaySales->sum(function ($sale) {
            return $sale->saleItems->sum('quantity');
        });

        $topProducts = Product::with('saleItems')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'total_sold' => $product->saleItems->sum('quantity'),
                    'revenue' => $product->saleItems->sum('line_total'),
                ];
            })
            ->sortByDesc('total_sold')
            ->take(10)
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'today_revenue' => $totalRevenue,
                'today_transactions' => $totalTransactions,
                'today_items_sold' => $totalItems,
                'top_products' => $topProducts,
            ],
        ]);
    }

    public function salesTrend(Request $request)
    {
        $request->validate([
            'days' => 'required|integer|min:1|max:30',
        ]);

        $days = $request->days ?? 7;
        $trend = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();
            $sales = Sale::whereBetween('created_at', [
                $date,
                $date->copy()->endOfDay(),
            ])->get();

            $trend[] = [
                'date' => $date->format('Y-m-d'),
                'revenue' => $sales->sum('total'),
                'transactions' => $sales->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $trend,
        ]);
    }
}

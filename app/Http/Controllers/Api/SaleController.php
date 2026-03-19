<?php

namespace App\Http\Controllers\Api;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;

class SaleController extends Controller
{
    public function index()
    {
        $sales = Sale::with(['user', 'customer', 'saleItems', 'payments'])
            ->latest()
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'customer_id' => 'nullable|exists:customers,id',
            'subtotal' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.line_total' => 'required|numeric|min:0',
        ]);

        try {
            $sale = Sale::create([
                'public_reference' => 'SL-' . date('YmdHis'),
                'user_id' => $request->user_id,
                'customer_id' => $request->customer_id,
                'subtotal' => $request->subtotal,
                'discount' => $request->discount ?? 0,
                'tax' => $request->tax ?? 0,
                'total' => $request->total,
                'status' => 'paid',
            ]);

            // Add sale items and deduct ingredients from inventory
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                    'cost' => $product->cost,
                ]);

                // Deduct linked ingredients from inventory
                $this->deductProductIngredients($product, $item['quantity']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Sale recorded successfully',
                'data' => $sale->load(['user', 'customer', 'saleItems', 'payments']),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating sale: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Deduct linked ingredients from inventory when a product is sold
     */
    private function deductProductIngredients(Product $product, $quantity)
    {
        $ingredients = $product->ingredients()->with('inventoryItem')->get();

        foreach ($ingredients as $ingredient) {
            $totalQuantityToDeduct = $ingredient->quantity * $quantity;
            
            $inventoryItem = $ingredient->inventoryItem;
            if ($inventoryItem) {
                $inventoryItem->decrement('quantity', $totalQuantityToDeduct);

                // Record stock movement
                StockMovement::create([
                    'inventory_item_id' => $inventoryItem->id,
                    'movement_type' => 'sale',
                    'quantity' => $totalQuantityToDeduct,
                    'unit_cost' => 0,
                    'reference_id' => null,
                    'notes' => 'Auto-deducted for sale of ' . $product->name,
                ]);
            }
        }
    }

    public function show(Sale $sale)
    {
        return response()->json([
            'success' => true,
            'data' => $sale->load(['user', 'customer', 'saleItems', 'payments']),
        ]);
    }

    public function recordPayment(Request $request, Sale $sale)
    {
        $request->validate([
            'method' => 'required|string',
            'amount' => 'required|numeric|min:0',
            'details' => 'nullable|json',
        ]);

        $payment = $sale->payments()->create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully',
            'data' => $payment,
        ], Response::HTTP_CREATED);
    }

    public function getSalesByDateRange(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ]);

        $sales = Sale::whereBetween('created_at', [
            $request->start_date . ' 00:00:00',
            $request->end_date . ' 23:59:59',
        ])->with(['user', 'customer', 'saleItems', 'payments'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales,
        ]);
    }

    public function getDailySales()
    {
        $today = now()->startOfDay();
        
        $sales = Sale::whereBetween('created_at', [
            $today,
            $today->copy()->endOfDay(),
        ])->with(['user', 'customer', 'saleItems', 'payments'])
            ->latest()
            ->get();

        $totalRevenue = $sales->sum('total');
        $totalTransactions = $sales->count();
        $totalItems = $sales->sum(function ($sale) {
            return $sale->saleItems->sum('quantity');
        });

        return response()->json([
            'success' => true,
            'data' => [
                'sales' => $sales,
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_transactions' => $totalTransactions,
                    'total_items' => $totalItems,
                    'date' => $today->format('Y-m-d'),
                ],
            ],
        ]);
    }
}

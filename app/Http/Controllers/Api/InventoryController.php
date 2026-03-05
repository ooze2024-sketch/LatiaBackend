<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;

class InventoryController extends Controller
{
    public function index()
    {
        $items = InventoryItem::with('product')->get();
        
        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'name' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'reorder_level' => 'nullable|numeric|min:0',
        ]);

        $item = InventoryItem::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Inventory item created successfully',
            'data' => $item->load('product'),
        ], Response::HTTP_CREATED);
    }

    public function show(InventoryItem $inventoryItem)
    {
        return response()->json([
            'success' => true,
            'data' => $inventoryItem->load(['product', 'stockMovements']),
        ]);
    }

    public function update(Request $request, InventoryItem $inventoryItem)
    {
        $request->validate([
            'name' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string',
            'reorder_level' => 'nullable|numeric|min:0',
        ]);

        $inventoryItem->update($request->only(['name', 'quantity', 'unit', 'reorder_level']));

        return response()->json([
            'success' => true,
            'message' => 'Inventory item updated successfully',
            'data' => $inventoryItem->load('product'),
        ]);
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Inventory item deleted successfully',
        ]);
    }

    public function addMovement(Request $request, InventoryItem $inventoryItem)
    {
        $request->validate([
            'movement_type' => 'required|in:purchase,adjustment,sale,transfer',
            'quantity' => 'required|numeric',
            'unit_cost' => 'nullable|numeric|min:0',
            'reference_id' => 'nullable|numeric',
            'notes' => 'nullable|string',
        ]);

        $movement = $inventoryItem->stockMovements()->create($request->all());

        // Update inventory quantity
        if ($request->movement_type === 'sale' || $request->movement_type === 'adjustment' && $request->quantity < 0) {
            $inventoryItem->decrement('quantity', abs($request->quantity));
        } else {
            $inventoryItem->increment('quantity', $request->quantity);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock movement recorded successfully',
            'data' => $movement,
        ], Response::HTTP_CREATED);
    }

    public function movements(InventoryItem $inventoryItem)
    {
        $movements = $inventoryItem->stockMovements()->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $movements,
        ]);
    }
}

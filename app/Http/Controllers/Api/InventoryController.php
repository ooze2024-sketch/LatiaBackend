<?php

namespace App\Http\Controllers\Api;

use App\Models\InventoryItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class InventoryController extends Controller
{
    private const CATALOG_CACHE_TTL_SECONDS = 120;

    private function getCatalogCacheVersion(): int
    {
        return (int) Cache::rememberForever('catalog:version', fn () => 1);
    }

    private function bumpCatalogCacheVersion(): void
    {
        $current = (int) Cache::get('catalog:version', 1);
        Cache::forever('catalog:version', $current + 1);
    }

    public function index()
    {
        $cacheKey = 'inventory:index:v' . $this->getCatalogCacheVersion();

        $items = Cache::remember($cacheKey, self::CATALOG_CACHE_TTL_SECONDS, function () {
            return InventoryItem::query()
                ->select([
                    'id',
                    'product_id',
                    'name',
                    'quantity',
                    'unit',
                    'reorder_level',
                    'created_at',
                    'updated_at',
                ])
                ->with(['product:id,name'])
                ->orderBy('name')
                ->get();
        });
        
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
        $this->bumpCatalogCacheVersion();

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
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Inventory item updated successfully',
            'data' => $inventoryItem->load('product'),
        ]);
    }

    public function destroy(InventoryItem $inventoryItem)
    {
        $inventoryItem->delete();
        $this->bumpCatalogCacheVersion();

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

        $this->bumpCatalogCacheVersion();

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

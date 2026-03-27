<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class IngredientsController extends Controller
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

    /**
     * Get ingredients for multiple products in one request
     */
    public function getMultipleProductsIngredients(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $productIds = collect($request->product_ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (count($productIds) === 0) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $cacheKey = 'ingredients:batch:v' . $this->getCatalogCacheVersion() . ':' . md5(implode(',', $productIds));

        $ingredients = Cache::remember($cacheKey, self::CATALOG_CACHE_TTL_SECONDS, function () use ($productIds) {
            return DB::table('product_ingredients as pi')
                ->join('inventory_items as ii', 'ii.id', '=', 'pi.inventory_item_id')
                ->whereIn('pi.product_id', $productIds)
                ->select([
                    'pi.id',
                    'pi.product_id',
                    'pi.inventory_item_id',
                    'pi.quantity',
                    'ii.name as inventory_item_name',
                    'ii.unit as inventory_item_unit',
                ])
                ->orderBy('pi.product_id')
                ->orderBy('pi.id')
                ->get()
                ->groupBy('product_id')
                ->map(function ($rows) {
                    return $rows->map(function ($row) {
                        return [
                            'id' => (int) $row->id,
                            'product_id' => (int) $row->product_id,
                            'inventory_item_id' => (int) $row->inventory_item_id,
                            'quantity' => (float) $row->quantity,
                            'inventoryItem' => [
                                'id' => (int) $row->inventory_item_id,
                                'name' => $row->inventory_item_name,
                                'unit' => $row->inventory_item_unit,
                            ],
                        ];
                    })->values();
                });
        });

        return response()->json([
            'success' => true,
            'data' => $ingredients,
        ]);
    }

    /**
     * Get ingredients linked to a product
     */
    public function getProductIngredients(Product $product)
    {
        $cacheKey = 'ingredients:product:v' . $this->getCatalogCacheVersion() . ':' . $product->id;

        $ingredients = Cache::remember($cacheKey, self::CATALOG_CACHE_TTL_SECONDS, function () use ($product) {
            return $product->ingredients()
                ->select(['id', 'product_id', 'inventory_item_id', 'quantity'])
                ->with(['inventoryItem:id,name,unit'])
                ->orderBy('id')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => $ingredients,
        ]);
    }

    /**
     * Link ingredients to a product
     */
    public function linkIngredientsToProduct(Request $request, Product $product)
    {
        $request->validate([
            'ingredients' => 'required|array',
            'ingredients.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'ingredients.*.quantity' => 'required|numeric|min:0.001',
        ]);

        try {
            DB::transaction(function () use ($product, $request) {
                // Delete existing ingredients for this product
                $product->ingredients()->delete();

                $payload = collect($request->ingredients)
                    ->map(function ($ingredient) use ($product) {
                        return [
                            'product_id' => $product->id,
                            'inventory_item_id' => $ingredient['inventory_item_id'],
                            'quantity' => $ingredient['quantity'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })
                    ->all();

                if (count($payload) > 0) {
                    ProductIngredient::insert($payload);
                }
            });

            $this->bumpCatalogCacheVersion();

            return response()->json([
                'success' => true,
                'message' => 'Ingredients linked successfully',
                'data' => $product->ingredients()
                    ->select(['id', 'product_id', 'inventory_item_id', 'quantity'])
                    ->with(['inventoryItem:id,name,unit'])
                    ->orderBy('id')
                    ->get(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error linking ingredients: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a specific ingredient link
     */
    public function deleteIngredientLink(ProductIngredient $productIngredient)
    {
        try {
            $productIngredient->delete();
            $this->bumpCatalogCacheVersion();

            return response()->json([
                'success' => true,
                'message' => 'Ingredient link deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting ingredient link: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update ingredient quantity
     */
    public function updateIngredientQuantity(Request $request, ProductIngredient $productIngredient)
    {
        $request->validate([
            'quantity' => 'required|numeric|min:0.001',
        ]);

        try {
            $productIngredient->update(['quantity' => $request->quantity]);
            $this->bumpCatalogCacheVersion();

            return response()->json([
                'success' => true,
                'message' => 'Ingredient quantity updated successfully',
                'data' => $productIngredient->load('inventoryItem'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating ingredient quantity: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

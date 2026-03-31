<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
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

    private function resolveAvailableQuantity(Product $product): ?int
    {
        $ingredients = $product->ingredients;
        if ($ingredients->isEmpty()) {
            return null;
        }

        $availableQuantity = null;

        foreach ($ingredients as $ingredient) {
            $requiredPerProduct = (float) $ingredient->quantity;
            if ($requiredPerProduct <= 0) {
                continue;
            }

            $inventoryQuantity = (float) optional($ingredient->inventoryItem)->quantity;
            $possibleUnits = (int) floor($inventoryQuantity / $requiredPerProduct);

            $availableQuantity = is_null($availableQuantity)
                ? $possibleUnits
                : min($availableQuantity, $possibleUnits);
        }

        return max(0, $availableQuantity ?? 0);
    }

    private function serializeProduct(Product $product, bool $includeInventoryItems = false): array
    {
        $data = [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'category_id' => $product->category_id,
            'category' => $product->category
                ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ]
                : null,
            'cost' => $product->cost,
            'price' => $product->price,
            'description' => $product->description,
            'is_active' => (bool) $product->is_active,
            'image_path' => $product->image_path,
            'image_url' => $product->image_path
                ? url('/api/v1/products/' . $product->id . '/image')
                : null,
            'available_quantity' => $this->resolveAvailableQuantity($product),
            'created_at' => $product->created_at?->toIso8601String(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];

        if ($includeInventoryItems) {
            $data['inventory_items'] = $product->inventoryItems->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'quantity' => (float) $item->quantity,
                    'unit' => $item->unit,
                    'reorder_level' => (float) $item->reorder_level,
                ];
            })->values();
        }

        return $data;
    }

    public function index()
    {
        $cacheKey = 'products:index:v' . $this->getCatalogCacheVersion();

        $products = Cache::remember($cacheKey, self::CATALOG_CACHE_TTL_SECONDS, function () {
            return Product::query()
                ->select([
                    'id',
                    'sku',
                    'name',
                    'category_id',
                    'cost',
                    'price',
                    'description',
                    'is_active',
                    'image_path',
                    'created_at',
                    'updated_at',
                ])
                ->with([
                    'category:id,name',
                    'ingredients:id,product_id,inventory_item_id,quantity',
                    'ingredients.inventoryItem:id,quantity',
                ])
                ->orderBy('name')
                ->get()
                ->map(fn (Product $product) => $this->serializeProduct($product))
                ->values()
                ->all();
        });
        
        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'sku' => 'nullable|string|unique:products',
            'name' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($request->all());
        $product->load([
            'category:id,name',
            'ingredients:id,product_id,inventory_item_id,quantity',
            'ingredients.inventoryItem:id,quantity',
        ]);
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $this->serializeProduct($product),
        ], Response::HTTP_CREATED);
    }

    public function show(Product $product)
    {
        $product->load([
            'category:id,name',
            'inventoryItems:id,product_id,name,quantity,unit,reorder_level',
            'ingredients:id,product_id,inventory_item_id,quantity',
            'ingredients.inventoryItem:id,quantity',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->serializeProduct($product, true),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'sku' => 'nullable|string|unique:products,sku,' . $product->id,
            'name' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'cost' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $product->update($request->all());
        $product->load([
            'category:id,name',
            'ingredients:id,product_id,inventory_item_id,quantity',
            'ingredients.inventoryItem:id,quantity',
        ]);
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $this->serializeProduct($product),
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    public function byCategory($categoryId)
    {
        $products = Product::where('category_id', $categoryId)
            ->where('is_active', true)
            ->with([
                'category:id,name',
                'ingredients:id,product_id,inventory_item_id,quantity',
                'ingredients.inventoryItem:id,quantity',
            ])
            ->get()
            ->map(fn (Product $product) => $this->serializeProduct($product))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function image(Product $product)
    {
        if (!$product->image_path) {
            return response()->json([
                'success' => false,
                'message' => 'Product image not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($product->image_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Product image file not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $mimeType = $disk->mimeType($product->image_path) ?: 'application/octet-stream';

        return response($disk->get($product->image_path), Response::HTTP_OK, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function uploadImage(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
        ]);

        try {
            // Delete old image if it exists
            if ($product->image_path && \Storage::exists('public/' . $product->image_path)) {
                \Storage::delete('public/' . $product->image_path);
            }

            // Store new image
            $path = $request->file('image')->store('products', 'public');
            
            // Update product with image path
            $product->update(['image_path' => $path]);
            $product->load([
                'category:id,name',
                'ingredients:id,product_id,inventory_item_id,quantity',
                'ingredients.inventoryItem:id,quantity',
            ]);
            $this->bumpCatalogCacheVersion();

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image_url' => url('/api/v1/products/' . $product->id . '/image'),
                    'image_path' => $path,
                    'product' => $this->serializeProduct($product),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

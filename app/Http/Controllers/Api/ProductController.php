<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

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
                ->with(['category:id,name'])
                ->orderBy('name')
                ->get();
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
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('category'),
        ], Response::HTTP_CREATED);
    }

    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->load(['category', 'inventoryItems']),
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
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load('category'),
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
            ->with('category')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products,
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
            $this->bumpCatalogCacheVersion();

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image_url' => url('storage/' . $path),
                    'image_path' => $path,
                    'product' => $product->load('category'),
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

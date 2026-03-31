<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    private function bumpCatalogCacheVersion(): void
    {
        $current = (int) Cache::get('catalog:version', 1);
        Cache::forever('catalog:version', $current + 1);
    }

    public function index()
    {
        $categories = Category::withCount('products')->get();
        
        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:categories',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($request->all());
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category,
        ], Response::HTTP_CREATED);
    }

    public function show(Category $category)
    {
        return response()->json([
            'success' => true,
            'data' => $category->load('products'),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $category->update($request->all());
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        $this->bumpCatalogCacheVersion();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
}

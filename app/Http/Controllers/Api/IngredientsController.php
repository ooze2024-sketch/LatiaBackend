<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Models\ProductIngredient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;

class IngredientsController extends Controller
{
    /**
     * Get ingredients for multiple products in one request
     */
    public function getMultipleProductsIngredients(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $ingredients = ProductIngredient::whereIn('product_id', $request->product_ids)
            ->with('inventoryItem')
            ->get()
            ->groupBy('product_id');

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
        $ingredients = $product->ingredients()->with('inventoryItem')->get();

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
            // Delete existing ingredients for this product
            $product->ingredients()->delete();

            // Add new ingredients
            foreach ($request->ingredients as $ingredient) {
                ProductIngredient::create([
                    'product_id' => $product->id,
                    'inventory_item_id' => $ingredient['inventory_item_id'],
                    'quantity' => $ingredient['quantity'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Ingredients linked successfully',
                'data' => $product->ingredients()->with('inventoryItem')->get(),
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\OrderItem;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = Product::orderBy('id', 'desc')->paginate(10);

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }
    
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => $product
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'sku')->ignore($product->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],
            'stock_quantity' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $product
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        if (OrderItem::where('product_id', $product->id)->exists()) {
            throw ValidationException::withMessages([
                'product' => [
                    'Products with order history cannot be deleted.',
                ],
            ]);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
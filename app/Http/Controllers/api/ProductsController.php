<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Products;
use App\Http\Requests\StoreProductsRequest;
use App\Http\Requests\UpdateProductsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Products",
 *     description="Products"
 * )
 */
class ProductsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products",
     *     operationId="getProductsList",
     *     tags={"Products"},
     *     summary="Get list of products",
     *     description="Returns list of products with filtering options",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in product name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="low_stock",
     *         in="query",
     *         description="Show only products with low stock",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="out_of_stock",
     *         in="query",
     *         description="Show only products out of stock",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Productos obtenidos exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
                     type="object",
                     @OA\Property(property="id", type="integer"),
                     @OA\Property(property="name", type="string"),
                     @OA\Property(property="image_path", type="string", nullable=true),
                     @OA\Property(property="image_url", type="string", nullable=true),
                     @OA\Property(property="category_id", type="integer"),
                     @OA\Property(property="cost_price", type="number"),
                     @OA\Property(property="sale_price", type="number"),
                     @OA\Property(property="stock", type="integer"),
                     @OA\Property(property="min_stock", type="integer"),
                     @OA\Property(property="is_active", type="boolean")
                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Products::class);

        $query = Products::with('category');

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category_id')) {
            $query->byCategory($request->category_id);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        if ($request->boolean('out_of_stock')) {
            $query->outOfStock();
        }

        $products = $query->orderBy('name')->paginate(20);

        return response()->json([
            'success' => true,
            'message' => 'Productos obtenidos exitosamente',
            'data' => $products
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/products",
     *     operationId="storeProduct",
     *     tags={"Products"},
     *     summary="Create new product",
     *     description="Creates a new product",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name", "sale_price"},
     *                 @OA\Property(property="name", type="string", maxLength=100, description="Product name", example="Coca Cola 600ml"),
     *                 @OA\Property(property="image", type="string", format="binary", description="Product image (jpeg,png,jpg,gif max 2MB)"),
     *                 @OA\Property(property="category_id", type="integer", description="Category ID", example=1),
     *                 @OA\Property(property="cost_price", type="number", format="float", description="Cost price", example=15.50),
     *                 @OA\Property(property="sale_price", type="number", format="float", description="Sale price", example=20.00),
     *                 @OA\Property(property="stock", type="integer", description="Initial stock", example=100),
     *                 @OA\Property(property="min_stock", type="integer", description="Minimum stock alert", example=10),
     *                 @OA\Property(property="is_active", type="boolean", description="Product status", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Producto creado exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(StoreProductsRequest $request): JsonResponse
    {
        $this->authorize('create', Products::class);

        $validatedData = $request->validated();
        
        // Handle image upload
        $validatedData = $this->handleImageUpload($request, $validatedData);

        $product = Products::create($validatedData);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Producto creado exitosamente',
            'data' => $product
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/products/{id}",
     *     operationId="showProduct",
     *     tags={"Products"},
     *     summary="Get product information",
     *     description="Returns product data",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="Product ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Producto obtenido exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function show(Products $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Producto obtenido exitosamente',
            'data' => $product
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/products/{id}",
     *     operationId="updateProduct",
     *     tags={"Products"},
     *     summary="Update product",
     *     description="Updates product data",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="Product ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(property="name", type="string", maxLength=100, description="Product name", example="Coca Cola 600ml"),
     *                 @OA\Property(property="image", type="string", format="binary", description="Product image (jpeg,png,jpg,gif max 2MB)"),
     *                 @OA\Property(property="category_id", type="integer", description="Category ID", example=1),
     *                 @OA\Property(property="cost_price", type="number", format="float", description="Cost price", example=15.50),
     *                 @OA\Property(property="sale_price", type="number", format="float", description="Sale price", example=20.00),
     *                 @OA\Property(property="stock", type="integer", description="Current stock", example=100),
     *                 @OA\Property(property="min_stock", type="integer", description="Minimum stock alert", example=10),
     *                 @OA\Property(property="is_active", type="boolean", description="Product status", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Producto actualizado exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdateProductsRequest $request, Products $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validatedData = $request->validated();
        
        // Handle image upload and deletion
        $validatedData = $this->handleImageUpload($request, $validatedData, $product);

        $product->update($validatedData);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Producto actualizado exitosamente',
            'data' => $product
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}",
     *     operationId="deleteProduct",
     *     tags={"Products"},
     *     summary="Delete product",
     *     description="Deletes a product if not referenced by sales or purchases",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="Product ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Producto eliminado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete product with existing sales or purchases"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function destroy(Products $product): JsonResponse
    {
        $this->authorize('delete', $product);

        // Check if product has sales or purchases (when those tables exist)
        // TODO: Add validation when sale_items and purchase_items tables are implemented
        
        // Delete product image
        $product->deleteImage();
        
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Producto eliminado exitosamente'
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/products/{id}/toggle-status",
     *     operationId="toggleProductStatus",
     *     tags={"Products"},
     *     summary="Toggle product active status",
     *     description="Toggles the is_active status of a product",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="Product ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product status toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Estado del producto actualizado exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function toggleStatus(Products $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product->update(['is_active' => !$product->is_active]);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Estado del producto actualizado exitosamente',
            'data' => $product
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/products/{id}/adjust-stock",
     *     operationId="adjustProductStock",
     *     tags={"Products"},
     *     summary="Adjust product stock",
     *     description="Manually adjust product stock with reason",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="Product ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="adjustment", type="integer", description="Stock adjustment (+/-)", example=10),
     *             @OA\Property(property="reason", type="string", description="Reason for adjustment", example="Inventory count correction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock adjusted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Stock ajustado exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function adjustStock(Request $request, Products $product): JsonResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'adjustment' => 'required|integer',
            'reason' => 'required|string|max:255'
        ]);

        $newStock = max(0, $product->stock + $request->adjustment);
        $product->update(['stock' => $newStock]);
        $product->load('category');

        // TODO: Log the stock adjustment when audit trail is implemented

        return response()->json([
            'success' => true,
            'message' => 'Stock ajustado exitosamente',
            'data' => $product
        ]);
    }

    /**
     * Handle image upload for products
     */
    private function handleImageUpload($request, array $validatedData, ?Products $product = null): array
    {
        if ($request->hasFile('image')) {
            // Delete old image if updating existing product
            if ($product) {
                $product->deleteImage();
            }
            
            // Store new image
            $image = $request->file('image');
            $imagePath = $image->store('products', 'public');
            $validatedData['image_path'] = $imagePath;
        }

        return $validatedData;
    }

    /**
     * @OA\Delete(
     *     path="/api/products/{id}/image",
     *     operationId="deleteProductImage",
     *     tags={"Products"},
     *     summary="Delete product image",
     *     description="Deletes the image of a product",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         description="Product ID",
     *         required=true,
     *         in="path",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Imagen eliminada exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function deleteImage(Products $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product->deleteImage();
        $product->update(['image_path' => null]);
        $product->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Imagen eliminada exitosamente',
            'data' => $product
        ]);
    }
}

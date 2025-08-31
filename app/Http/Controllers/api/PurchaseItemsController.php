<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseItemsRequest;
use App\Http\Requests\UpdatePurchaseItemsRequest;
use App\Models\PurchaseItems;
use App\Models\Purchases;
use App\Models\Products;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Purchase Items",
 *     description="Gestión de items de compras"
 * )
 */
class PurchaseItemsController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(PurchaseItems::class, 'purchaseItem');
    }

    /**
     * @OA\Get(
     *     path="/api/purchase-items",
     *     tags={"Purchase Items"},
     *     summary="Listar items de compras",
     *     description="Obtiene una lista paginada de items de compras con filtros opcionales",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="purchase_id",
     *         in="query",
     *         description="Filtrar por ID de compra",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filtrar por ID de producto",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="min_quantity",
     *         in="query",
     *         description="Cantidad mínima",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="max_unit_cost",
     *         in="query",
     *         description="Costo unitario máximo",
     *         required=false,
     *         @OA\Schema(type="number")
     *     ),
     *     @OA\Parameter(
     *         name="with_relations",
     *         in="query",
     *         description="Incluir relaciones (producto y compra)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de items de compras",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/PurchaseItems")
     *             ),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseItems::query();

        // Filtros
        if ($request->filled('purchase_id')) {
            $query->byPurchase($request->purchase_id);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        if ($request->filled('min_quantity')) {
            $query->minQuantity($request->min_quantity);
        }

        if ($request->filled('max_unit_cost')) {
            $query->maxUnitCost($request->max_unit_cost);
        }

        // Incluir relaciones si se solicita
        if ($request->boolean('with_relations')) {
            $query->withRelations();
        }

        $query->orderByLatest();

        $perPage = $request->get('per_page', 15);
        $purchaseItems = $query->paginate($perPage);

        return response()->json($purchaseItems);
    }

    /**
     * @OA\Post(
     *     path="/api/purchase-items",
     *     tags={"Purchase Items"},
     *     summary="Crear item de compra",
     *     description="Crea un nuevo item de compra",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"purchase_id", "product_id", "quantity", "unit_cost"},
     *             @OA\Property(property="purchase_id", type="integer", description="ID de la compra"),
     *             @OA\Property(property="product_id", type="integer", description="ID del producto"),
     *             @OA\Property(property="quantity", type="integer", description="Cantidad"),
     *             @OA\Property(property="unit_cost", type="number", format="float", description="Costo unitario")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item de compra creado exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Item de compra creado exitosamente"),
     *             @OA\Property(property="data", ref="#/components/schemas/PurchaseItems")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Errores de validación"
     *     )
     * )
     */
    public function store(StorePurchaseItemsRequest $request): JsonResponse
    {
        $purchaseItem = PurchaseItems::create($request->validated());
        $purchaseItem->load(['product:id,name,barcode', 'purchase:id,supplier_name']);

        return response()->json([
            'message' => 'Item de compra creado exitosamente',
            'data' => $purchaseItem
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/purchase-items/{id}",
     *     tags={"Purchase Items"},
     *     summary="Obtener item de compra",
     *     description="Obtiene los detalles de un item de compra específico",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del item de compra",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del item de compra",
     *         @OA\JsonContent(ref="#/components/schemas/PurchaseItems")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Item de compra no encontrado"
     *     )
     * )
     */
    public function show(PurchaseItems $purchaseItem): JsonResponse
    {
        $purchaseItem->load(['product:id,name,barcode,category_id', 'purchase:id,supplier_name,purchase_date,total']);
        
        return response()->json($purchaseItem);
    }

    /**
     * @OA\Put(
     *     path="/api/purchase-items/{id}",
     *     tags={"Purchase Items"},
     *     summary="Actualizar item de compra",
     *     description="Actualiza los datos de un item de compra existente",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del item de compra",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="purchase_id", type="integer", description="ID de la compra"),
     *             @OA\Property(property="product_id", type="integer", description="ID del producto"),
     *             @OA\Property(property="quantity", type="integer", description="Cantidad"),
     *             @OA\Property(property="unit_cost", type="number", format="float", description="Costo unitario")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item de compra actualizado exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Item de compra actualizado exitosamente"),
     *             @OA\Property(property="data", ref="#/components/schemas/PurchaseItems")
     *         )
     *     )
     * )
     */
    public function update(UpdatePurchaseItemsRequest $request, PurchaseItems $purchaseItem): JsonResponse
    {
        $purchaseItem->update($request->validated());
        $purchaseItem->load(['product:id,name,barcode', 'purchase:id,supplier_name']);

        return response()->json([
            'message' => 'Item de compra actualizado exitosamente',
            'data' => $purchaseItem
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/purchase-items/{id}",
     *     tags={"Purchase Items"},
     *     summary="Eliminar item de compra",
     *     description="Elimina un item de compra existente",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del item de compra",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item de compra eliminado exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Item de compra eliminado exitosamente")
     *         )
     *     )
     * )
     */
    public function destroy(PurchaseItems $purchaseItem): JsonResponse
    {
        $purchaseItem->delete();

        return response()->json([
            'message' => 'Item de compra eliminado exitosamente'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/purchases/{purchase_id}/items",
     *     tags={"Purchase Items"},
     *     summary="Obtener items de una compra",
     *     description="Obtiene todos los items de una compra específica",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="purchase_id",
     *         in="path",
     *         description="ID de la compra",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Items de la compra",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/PurchaseItems")
     *         )
     *     )
     * )
     */
    public function getByPurchase(Request $request, int $purchaseId): JsonResponse
    {
        // Verificar que la compra existe
        $purchase = Purchases::findOrFail($purchaseId);
        
        // Verificar autorización para ver la compra
        $this->authorize('view', $purchase);

        $items = PurchaseItems::byPurchase($purchaseId)
            ->withProduct()
            ->orderByLatest()
            ->get();

        return response()->json($items);
    }

    /**
     * @OA\Get(
     *     path="/api/purchase-items/stats",
     *     tags={"Purchase Items"},
     *     summary="Estadísticas de items de compras",
     *     description="Obtiene estadísticas generales de los items de compras",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas de items de compras",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="general_stats", type="object"),
     *             @OA\Property(
     *                 property="most_purchased_products", 
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="recent_purchases", 
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="cost_analysis", 
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="top_suppliers", 
     *                 type="array",
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'general_stats' => PurchaseItems::getStats(),
            'most_purchased_products' => PurchaseItems::getMostPurchasedProducts(10),
            'recent_purchases' => PurchaseItems::getRecentPurchases(15),
            'cost_analysis' => PurchaseItems::getCostAnalysis(),
            'top_suppliers' => PurchaseItems::getTopSuppliersByQuantity(10)
        ];

        return response()->json($stats);
    }

    /**
     * @OA\Get(
     *     path="/api/products/{product_id}/purchase-history",
     *     tags={"Purchase Items"},
     *     summary="Historial de compras de un producto",
     *     description="Obtiene el historial de compras de un producto específico",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="product_id",
     *         in="path",
     *         description="ID del producto",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Número máximo de registros",
     *         required=false,
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Historial de compras del producto",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/PurchaseItems")
     *         )
     *     )
     * )
     */
    public function getPurchaseHistory(Request $request, int $productId): JsonResponse
    {
        // Verificar que el producto existe
        $product = Products::findOrFail($productId);
        
        // Verificar autorización para ver el producto
        $this->authorize('view', $product);

        $limit = $request->get('limit', 20);
        
        $history = PurchaseItems::byProduct($productId)
            ->withPurchase()
            ->orderByLatest()
            ->limit($limit)
            ->get();

        return response()->json($history);
    }
}

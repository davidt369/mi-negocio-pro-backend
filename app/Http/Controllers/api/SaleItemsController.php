<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleItemsRequest;
use App\Http\Requests\UpdateSaleItemsRequest;
use App\Models\SaleItems;
use App\Models\Sales;
use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Sale Items",
 *     description="Gestión de items de venta - detalle de productos vendidos"
 * )
 */
class SaleItemsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/sale-items",
     *     summary="Listar items de venta",
     *     description="Obtiene la lista de items de venta con filtros opcionales",
     *     operationId="getSaleItems",
     *     tags={"Sale Items"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="sale_id",
     *         in="query",
     *         description="Filtrar por ID de venta",
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
     *         name="date_from",
     *         in="query",
     *         description="Fecha desde (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Fecha hasta (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Número de elementos por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, minimum=1, maximum=100)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de items de venta",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SaleItems::class);

        $query = SaleItems::query()
            ->withProduct()
            ->withSale()
            ->orderByCreated();

        // Filtros
        if ($request->filled('sale_id')) {
            $query->bySale($request->sale_id);
        }

        if ($request->filled('product_id')) {
            $query->byProduct($request->product_id);
        }

        // Filtros por fecha a través de la venta
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $query->whereHas('sale', function ($q) use ($request) {
                if ($request->filled('date_from')) {
                    $q->where('sale_date', '>=', $request->date_from);
                }
                if ($request->filled('date_to')) {
                    $q->where('sale_date', '<=', $request->date_to);
                }
            });
        }

        $perPage = min($request->get('per_page', 15), 100);
        $saleItems = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $saleItems->items(),
            'meta' => [
                'total' => $saleItems->total(),
                'per_page' => $saleItems->perPage(),
                'current_page' => $saleItems->currentPage(),
                'last_page' => $saleItems->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/sale-items",
     *     summary="Crear nuevo item de venta",
     *     description="Crea un nuevo item de venta y actualiza el stock del producto",
     *     operationId="createSaleItem",
     *     tags={"Sale Items"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"sale_id", "product_id", "quantity", "unit_price"},
     *             @OA\Property(property="sale_id", type="integer", description="ID de la venta"),
     *             @OA\Property(property="product_id", type="integer", description="ID del producto"),
     *             @OA\Property(property="quantity", type="integer", minimum=1, description="Cantidad a vender"),
     *             @OA\Property(property="unit_price", type="number", format="decimal", minimum=0, description="Precio unitario")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Item de venta creado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item de venta creado exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Datos inválidos o stock insuficiente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Venta o producto no encontrado")
     * )
     */
    public function store(StoreSaleItemsRequest $request): JsonResponse
    {
        $this->authorize('create', SaleItems::class);

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Verificar que la venta existe
            $sale = Sales::findOrFail($validated['sale_id']);
            
            // Verificar que el producto existe y hay stock suficiente
            $product = Products::findOrFail($validated['product_id']);
            
            if ($product->stock < $validated['quantity']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stock insuficiente para el producto: ' . $product->name,
                    'data' => [
                        'available_stock' => $product->stock,
                        'requested_quantity' => $validated['quantity'],
                    ],
                ], 400);
            }

            $saleItem = SaleItems::create($validated);
            
            // Cargar relaciones para la respuesta
            $saleItem->load(['product:id,name,barcode', 'sale:id,sale_number,customer_name']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item de venta creado exitosamente',
                'data' => $saleItem,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el item de venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sale-items/{id}",
     *     summary="Obtener item de venta específico",
     *     description="Obtiene los detalles de un item de venta específico",
     *     operationId="getSaleItem",
     *     tags={"Sale Items"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del item de venta",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del item de venta",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Item de venta no encontrado")
     * )
     */
    public function show(SaleItems $saleItem): JsonResponse
    {
        $this->authorize('view', $saleItem);

        $saleItem->load(['product:id,name,barcode,sale_price', 'sale:id,sale_number,customer_name,sale_date']);

        return response()->json([
            'success' => true,
            'data' => $saleItem,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/sale-items/{id}",
     *     summary="Actualizar item de venta",
     *     description="Actualiza un item de venta existente y ajusta el stock del producto",
     *     operationId="updateSaleItem",
     *     tags={"Sale Items"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del item de venta",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity", "unit_price"},
     *             @OA\Property(property="quantity", type="integer", minimum=1, description="Nueva cantidad"),
     *             @OA\Property(property="unit_price", type="number", format="decimal", minimum=0, description="Nuevo precio unitario")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item de venta actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item de venta actualizado exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Datos inválidos o stock insuficiente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Item de venta no encontrado")
     * )
     */
    public function update(UpdateSaleItemsRequest $request, SaleItems $saleItem): JsonResponse
    {
        $this->authorize('update', $saleItem);

        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $originalQuantity = $saleItem->quantity;
            $newQuantity = $validated['quantity'];
            $quantityDifference = $newQuantity - $originalQuantity;

            // Verificar stock disponible si se aumenta la cantidad
            if ($quantityDifference > 0) {
                $product = $saleItem->product;
                if ($product->stock < $quantityDifference) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Stock insuficiente para aumentar la cantidad',
                        'data' => [
                            'available_stock' => $product->stock,
                            'additional_needed' => $quantityDifference,
                        ],
                    ], 400);
                }
            }

            $saleItem->update($validated);
            
            // Cargar relaciones para la respuesta
            $saleItem->load(['product:id,name,barcode', 'sale:id,sale_number,customer_name']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item de venta actualizado exitosamente',
                'data' => $saleItem,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el item de venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/sale-items/{id}",
     *     summary="Eliminar item de venta",
     *     description="Elimina un item de venta y restaura el stock del producto",
     *     operationId="deleteSaleItem",
     *     tags={"Sale Items"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del item de venta",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item de venta eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Item de venta eliminado exitosamente")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Item de venta no encontrado")
     * )
     */
    public function destroy(SaleItems $saleItem): JsonResponse
    {
        $this->authorize('delete', $saleItem);

        try {
            DB::beginTransaction();

            $saleItem->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Item de venta eliminado exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el item de venta: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/sale-items/by-sale/{saleId}",
     *     summary="Obtener items de una venta específica",
     *     description="Obtiene todos los items que pertenecen a una venta específica",
     *     operationId="getSaleItemsBySale",
     *     tags={"Sale Items"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="saleId",
     *         in="path",
     *         description="ID de la venta",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Items de la venta",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="total_items", type="integer"),
     *                 @OA\Property(property="total_quantity", type="integer"),
     *                 @OA\Property(property="total_amount", type="number", format="decimal")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Venta no encontrada")
     * )
     */
    public function getBySale(int $saleId): JsonResponse
    {
        $this->authorize('viewAny', SaleItems::class);

        // Verificar que la venta existe
        $sale = Sales::findOrFail($saleId);

        $saleItems = SaleItems::bySale($saleId)
            ->withProduct()
            ->orderByCreated('asc')
            ->get();

        $summary = [
            'total_items' => $saleItems->count(),
            'total_quantity' => $saleItems->sum('quantity'),
            'total_amount' => $saleItems->sum('line_total'),
        ];

        return response()->json([
            'success' => true,
            'data' => $saleItems,
            'summary' => $summary,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/sale-items/stats",
     *     summary="Estadísticas de items de venta",
     *     description="Obtiene estadísticas de items de venta con filtros opcionales",
     *     operationId="getSaleItemsStats",
     *     tags={"Sale Items"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Fecha desde (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Fecha hasta (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Filtrar por ID de producto",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas de items de venta",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_items", type="integer"),
     *                 @OA\Property(property="total_quantity", type="integer"),
     *                 @OA\Property(property="total_amount", type="number", format="decimal"),
     *                 @OA\Property(property="average_unit_price", type="number", format="decimal"),
     *                 @OA\Property(property="most_sold_products", type="array", @OA\Items(
     *                     @OA\Property(property="product_id", type="integer"),
     *                     @OA\Property(property="product", type="object"),
     *                     @OA\Property(property="total_quantity", type="integer"),
     *                     @OA\Property(property="total_revenue", type="number", format="decimal"),
     *                     @OA\Property(property="times_sold", type="integer")
     *                 ))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SaleItems::class);

        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'product_id' => $request->get('product_id'),
        ];

        $stats = SaleItems::getStats($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}

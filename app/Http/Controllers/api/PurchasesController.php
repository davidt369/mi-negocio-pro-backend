<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchasesRequest;
use App\Http\Requests\UpdatePurchasesRequest;
use App\Models\Purchases;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Purchases",
 *     description="Gestión de compras/entradas de inventario"
 * )
 */
class PurchasesController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/purchases",
     *     summary="Listar compras",
     *     description="Obtiene la lista de compras con filtros opcionales",
     *     operationId="getPurchases",
     *     tags={"Purchases"},
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
     *         name="received_by",
     *         in="query",
     *         description="Filtrar por ID de usuario que recibió",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="supplier_name",
     *         in="query",
     *         description="Filtrar por nombre de proveedor",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *         description="Lista de compras",
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
        $this->authorize('viewAny', Purchases::class);

        $query = Purchases::query()
            ->with(['user:id,full_name'])
            ->orderByPurchaseDate();

        // Filtros
        if ($request->filled('date_from')) {
            $query->where('purchase_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('purchase_date', '<=', $request->date_to);
        }

        if ($request->filled('received_by')) {
            $query->byUser($request->received_by);
        }

        if ($request->filled('supplier_name')) {
            $query->bySupplier($request->supplier_name);
        }

        $perPage = min($request->get('per_page', 15), 100);
        $purchases = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $purchases->items(),
            'meta' => [
                'total' => $purchases->total(),
                'per_page' => $purchases->perPage(),
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/purchases",
     *     summary="Crear nueva compra",
     *     description="Crea una nueva compra/entrada de inventario",
     *     operationId="createPurchase",
     *     tags={"Purchases"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"received_by"},
     *             @OA\Property(property="supplier_name", type="string", example="Distribuidora ABC", description="Nombre del proveedor"),
     *             @OA\Property(property="notes", type="string", example="Compra de productos varios", description="Notas adicionales"),
     *             @OA\Property(property="purchase_date", type="string", format="date", example="2025-08-24", description="Fecha de la compra"),
     *             @OA\Property(property="received_by", type="integer", example=1, description="ID del usuario que recibió")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Compra creada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compra creada exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Datos inválidos"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function store(StorePurchasesRequest $request): JsonResponse
    {
        $this->authorize('create', Purchases::class);

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Verificar que el usuario existe
            $user = Users::findOrFail($validated['received_by']);

            $purchase = Purchases::create($validated);
            
            // Cargar relaciones para la respuesta
            $purchase->load(['user:id,full_name']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compra creada exitosamente',
                'data' => $purchase,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la compra: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/purchases/{id}",
     *     summary="Obtener compra específica",
     *     description="Obtiene los detalles de una compra específica con sus items",
     *     operationId="getPurchase",
     *     tags={"Purchases"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la compra",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles de la compra",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Compra no encontrada")
     * )
     */
    public function show(Purchases $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);

        $purchase->load([
            'user:id,full_name',
            'items.product:id,name,barcode'
        ]);

        return response()->json([
            'success' => true,
            'data' => $purchase,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/purchases/{id}",
     *     summary="Actualizar compra",
     *     description="Actualiza una compra existente",
     *     operationId="updatePurchase",
     *     tags={"Purchases"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la compra",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="supplier_name", type="string", example="Distribuidora XYZ", description="Nuevo nombre del proveedor"),
     *             @OA\Property(property="notes", type="string", example="Notas actualizadas", description="Nuevas notas"),
     *             @OA\Property(property="purchase_date", type="string", format="date", example="2025-08-25", description="Nueva fecha de compra"),
     *             @OA\Property(property="received_by", type="integer", example=2, description="Nuevo ID del usuario que recibió")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compra actualizada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compra actualizada exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Datos inválidos"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Compra no encontrada")
     * )
     */
    public function update(UpdatePurchasesRequest $request, Purchases $purchase): JsonResponse
    {
        $this->authorize('update', $purchase);

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Verificar que el usuario existe si se está cambiando
            if (isset($validated['received_by'])) {
                Users::findOrFail($validated['received_by']);
            }

            $purchase->update($validated);
            
            // Cargar relaciones para la respuesta
            $purchase->load(['user:id,full_name']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compra actualizada exitosamente',
                'data' => $purchase,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la compra: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/purchases/{id}",
     *     summary="Eliminar compra",
     *     description="Elimina una compra y todos sus items asociados",
     *     operationId="deletePurchase",
     *     tags={"Purchases"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la compra",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compra eliminada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compra eliminada exitosamente")
     *         )
     *     ),
     *     @OA\Response(response=400, description="No se puede eliminar esta compra"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Compra no encontrada")
     * )
     */
    public function destroy(Purchases $purchase): JsonResponse
    {
        $this->authorize('delete', $purchase);

        if (!$purchase->canDelete()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar esta compra. Solo se pueden eliminar compras de los últimos 30 días.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $purchase->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compra eliminada exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la compra: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/purchases/stats",
     *     summary="Estadísticas de compras",
     *     description="Obtiene estadísticas de compras con filtros opcionales",
     *     operationId="getPurchasesStats",
     *     tags={"Purchases"},
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
     *         name="received_by",
     *         in="query",
     *         description="Filtrar por ID de usuario",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="supplier_name",
     *         in="query",
     *         description="Filtrar por nombre de proveedor",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas de compras",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_purchases", type="integer"),
     *                 @OA\Property(property="total_amount", type="number", format="decimal"),
     *                 @OA\Property(property="average_amount", type="number", format="decimal"),
     *                 @OA\Property(property="monthly_purchases", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="top_suppliers", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchases::class);

        $filters = [
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'received_by' => $request->get('received_by'),
            'supplier_name' => $request->get('supplier_name'),
        ];

        $stats = Purchases::getStats($filters);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/purchases/recent",
     *     summary="Compras recientes",
     *     description="Obtiene las compras más recientes",
     *     operationId="getRecentPurchases",
     *     tags={"Purchases"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Número de compras a obtener",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, minimum=1, maximum=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compras recientes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido")
     * )
     */
    public function recent(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchases::class);

        $limit = min($request->get('limit', 10), 50);
        $purchases = Purchases::getRecent($limit);

        return response()->json([
            'success' => true,
            'data' => $purchases,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/purchases/{id}/summary",
     *     summary="Resumen de compra",
     *     description="Obtiene un resumen detallado de una compra específica",
     *     operationId="getPurchaseSummary",
     *     tags={"Purchases"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la compra",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resumen de la compra",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="purchase_id", type="integer"),
     *                 @OA\Property(property="supplier_name", type="string"),
     *                 @OA\Property(property="total_items", type="integer"),
     *                 @OA\Property(property="total_amount", type="string"),
     *                 @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Prohibido"),
     *     @OA\Response(response=404, description="Compra no encontrada")
     * )
     */
    public function summary(Purchases $purchase): JsonResponse
    {
        $this->authorize('view', $purchase);

        $summary = $purchase->getSummary();

        return response()->json([
            'success' => true,
            'data' => $summary,
        ]);
    }
}

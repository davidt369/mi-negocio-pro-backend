<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSalesRequest;
use App\Http\Requests\UpdateSalesRequest;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class SalesController extends Controller
{
    /**
     * Lista ventas con filtros opcionales.
     *
     * @OA\Get(
     *     path="/api/sales",
     *     operationId="getSales",
     *     tags={"Sales"},
     *     summary="Lista ventas con filtros opcionales",
     *     description="Obtiene lista paginada de ventas con filtros por fecha, vendedor, método de pago y búsqueda",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         description="Búsqueda por número de venta o nombre de cliente",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=false,
     *         description="Fecha de inicio para filtrar ventas",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=false,
     *         description="Fecha de fin para filtrar ventas",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="payment_method",
     *         in="query",
     *         required=false,
     *         description="Filtrar por método de pago",
     *         @OA\Schema(type="string", enum={"cash", "card", "transfer", "credit"})
     *     ),
     *     @OA\Parameter(
     *         name="sold_by",
     *         in="query",
     *         required=false,
     *         description="Filtrar por ID del vendedor",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Número de elementos por página",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de ventas obtenida exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Ventas obtenidas exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="sale_number", type="string"),
     *                         @OA\Property(property="customer_name", type="string"),
     *                         @OA\Property(property="total", type="number"),
     *                         @OA\Property(property="payment_method", type="string"),
     *                         @OA\Property(property="payment_method_label", type="string"),
     *                         @OA\Property(property="sale_date", type="string"),
     *                         @OA\Property(property="sold_by", type="integer"),
     *                         @OA\Property(property="notes", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Prohibido",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No tienes permisos para realizar esta acción")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sales::class);

        $query = Sales::with('seller:id,full_name');

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('start_date')) {
            if ($request->filled('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            } else {
                $query->dateRange($request->start_date);
            }
        }

        if ($request->filled('payment_method')) {
            $query->byPaymentMethod($request->payment_method);
        }

        if ($request->filled('sold_by')) {
            $query->bySeller($request->sold_by);
        }

        // Default ordering
        $query->latest('sale_date')->latest('created_at');

        $perPage = $request->input('per_page', 15);
        $sales = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Ventas obtenidas exitosamente',
            'data' => $sales
        ]);
    }

    /**
     * Crear nueva venta.
     *
     * @OA\Post(
     *     path="/api/sales",
     *     operationId="createSale",
     *     tags={"Sales"},
     *     summary="Crear nueva venta",
     *     description="Crea una nueva venta en el sistema",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos de la venta",
     *         @OA\JsonContent(
     *             required={"total", "payment_method", "sale_date", "sold_by"},
     *             @OA\Property(property="customer_name", type="string", example="Juan Pérez"),
     *             @OA\Property(property="total", type="number", example=15500.50),
     *             @OA\Property(property="payment_method", type="string", enum={"cash", "card", "transfer", "credit"}, example="cash"),
     *             @OA\Property(property="notes", type="string", example="Venta con descuento especial"),
     *             @OA\Property(property="sale_date", type="string", format="date", example="2025-08-24"),
     *             @OA\Property(property="sold_by", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Venta creada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Venta creada exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="sale_number", type="string"),
     *                 @OA\Property(property="customer_name", type="string"),
     *                 @OA\Property(property="total", type="number"),
     *                 @OA\Property(property="payment_method", type="string"),
     *                 @OA\Property(property="payment_method_label", type="string"),
     *                 @OA\Property(property="sale_date", type="string"),
     *                 @OA\Property(property="sold_by", type="integer"),
     *                 @OA\Property(property="notes", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(StoreSalesRequest $request): JsonResponse
    {
        $this->authorize('create', Sales::class);

        $sale = Sales::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Venta creada exitosamente',
            'data' => $sale->load('seller:id,full_name')
        ], 201);
    }

    /**
     * Mostrar venta específica.
     *
     * @OA\Get(
     *     path="/api/sales/{id}",
     *     operationId="getSale",
     *     tags={"Sales"},
     *     summary="Obtener venta específica",
     *     description="Obtiene los detalles de una venta específica",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la venta",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Venta obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Venta obtenida exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="sale_number", type="string"),
     *                 @OA\Property(property="customer_name", type="string"),
     *                 @OA\Property(property="total", type="number"),
     *                 @OA\Property(property="payment_method", type="string"),
     *                 @OA\Property(property="payment_method_label", type="string"),
     *                 @OA\Property(property="sale_date", type="string"),
     *                 @OA\Property(property="sold_by", type="integer"),
     *                 @OA\Property(property="notes", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Venta no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Venta no encontrada")
     *         )
     *     )
     * )
     */
    public function show(Sales $sale): JsonResponse
    {
        $this->authorize('view', $sale);

        return response()->json([
            'success' => true,
            'message' => 'Venta obtenida exitosamente',
            'data' => $sale->load('seller:id,full_name')
        ]);
    }

    /**
     * Actualizar venta.
     *
     * @OA\Put(
     *     path="/api/sales/{id}",
     *     operationId="updateSale",
     *     tags={"Sales"},
     *     summary="Actualizar venta",
     *     description="Actualiza los datos de una venta existente",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la venta",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos actualizados de la venta",
     *         @OA\JsonContent(type="object")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Venta actualizada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Venta actualizada exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function update(UpdateSalesRequest $request, Sales $sale): JsonResponse
    {
        $this->authorize('update', $sale);

        $sale->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Venta actualizada exitosamente',
            'data' => $sale->load('seller:id,full_name')
        ]);
    }

    /**
     * Eliminar venta.
     *
     * @OA\Delete(
     *     path="/api/sales/{id}",
     *     operationId="deleteSale",
     *     tags={"Sales"},
     *     summary="Eliminar venta",
     *     description="Elimina una venta del sistema",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID de la venta",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Venta eliminada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Venta eliminada exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Venta no encontrada",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Venta no encontrada")
     *         )
     *     )
     * )
     */
    public function destroy(Sales $sale): JsonResponse
    {
        $this->authorize('delete', $sale);

        $sale->delete();

        return response()->json([
            'success' => true,
            'message' => 'Venta eliminada exitosamente'
        ]);
    }

    /**
     * Obtener estadísticas de ventas.
     *
     * @OA\Get(
     *     path="/api/sales/stats",
     *     operationId="getSalesStats",
     *     tags={"Sales"},
     *     summary="Obtener estadísticas de ventas",
     *     description="Obtiene estadísticas de ventas por diferentes períodos",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estadísticas obtenidas exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Estadísticas obtenidas exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="today_total", type="number"),
     *                 @OA\Property(property="today_count", type="integer"),
     *                 @OA\Property(property="week_total", type="number"),
     *                 @OA\Property(property="month_total", type="number"),
     *                 @OA\Property(
     *                     property="payment_methods",
     *                     type="object",
     *                     @OA\Property(property="cash", type="number"),
     *                     @OA\Property(property="card", type="number"),
     *                     @OA\Property(property="transfer", type="number"),
     *                     @OA\Property(property="credit", type="number")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function stats(): JsonResponse
    {
        $this->authorize('viewAny', Sales::class);

        $todayTotal = Sales::totalByPeriod('today');
        $todayCount = Sales::todaySales()->count();
        $weekTotal = Sales::totalByPeriod('week');
        $monthTotal = Sales::totalByPeriod('month');

        // Estadísticas por método de pago (mes actual)
        $paymentMethods = Sales::monthSales()
            ->selectRaw('payment_method, SUM(total) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas obtenidas exitosamente',
            'data' => [
                'today_total' => $todayTotal,
                'today_count' => $todayCount,
                'week_total' => $weekTotal,
                'month_total' => $monthTotal,
                'payment_methods' => [
                    'cash' => $paymentMethods['cash'] ?? 0,
                    'card' => $paymentMethods['card'] ?? 0,
                    'transfer' => $paymentMethods['transfer'] ?? 0,
                    'credit' => $paymentMethods['credit'] ?? 0,
                ]
            ]
        ]);
    }
}

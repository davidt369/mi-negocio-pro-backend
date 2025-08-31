<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Business Intelligence",
 *     description="Funciones de inteligencia de negocio para chatbot IA"
 * )
 */
class BusinessIntelligenceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/business-intelligence/revenue/{period}",
     *     tags={"Business Intelligence"},
     *     summary="Obtener ingresos por período",
     *     description="Obtiene ingresos y transacciones para today, this_month o last_month",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="period",
     *         in="path",
     *         required=true,
     *         description="Período: today, this_month, last_month",
     *         @OA\Schema(type="string", enum={"today", "this_month", "last_month"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Ingresos y transacciones del período",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="period", type="string"),
     *             @OA\Property(property="revenue", type="number", format="float"),
     *             @OA\Property(property="transactions", type="integer"),
     *             @OA\Property(property="avg_ticket", type="number", format="float")
     *         )
     *     )
     * )
     */
    public function getRevenue(string $period = 'today'): JsonResponse
    {
        // Validar período
        if (!in_array($period, ['today', 'this_month', 'last_month'])) {
            return response()->json(['error' => 'Período inválido'], 400);
        }

        $result = DB::select('SELECT * FROM get_revenue(?)', [$period]);
        $data = $result[0] ?? null;

        if (!$data) {
            return response()->json([
                'period' => $period,
                'revenue' => 0,
                'transactions' => 0,
                'avg_ticket' => 0
            ]);
        }

        return response()->json([
            'period' => $period,
            'revenue' => (float) $data->revenue,
            'transactions' => (int) $data->transactions,
            'avg_ticket' => $data->transactions > 0 ? (float) $data->revenue / $data->transactions : 0
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/business-intelligence/product-stock/{productName}",
     *     tags={"Business Intelligence"},
     *     summary="Consultar stock de productos",
     *     description="Busca productos por nombre y muestra su estado de stock",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="productName",
     *         in="path",
     *         required=true,
     *         description="Nombre del producto a buscar",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado de stock de productos encontrados",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="stock", type="integer"),
     *                 @OA\Property(property="min_stock", type="integer"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function getProductStock(string $productName): JsonResponse
    {
        $results = DB::select('SELECT * FROM get_product_stock(?)', [$productName]);
        
        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/business-intelligence/top-selling-products",
     *     tags={"Business Intelligence"},
     *     summary="Productos más vendidos",
     *     description="Obtiene los productos más vendidos en los últimos días",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         required=false,
     *         description="Número de días hacia atrás (default: 30)",
     *         @OA\Schema(type="integer", default=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de productos más vendidos",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="product_name", type="string"),
     *                 @OA\Property(property="total_sold", type="integer"),
     *                 @OA\Property(property="revenue", type="number", format="float")
     *             )
     *         )
     *     )
     * )
     */
    public function getTopSellingProducts(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $results = DB::select('SELECT * FROM get_top_selling_products(?)', [$days]);
        
        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/business-intelligence/daily-sales",
     *     tags={"Business Intelligence"},
     *     summary="Ventas diarias",
     *     description="Obtiene reporte de ventas agrupadas por día",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Número de días a mostrar (default: 30)",
     *         @OA\Schema(type="integer", default=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reporte de ventas diarias",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="sale_date", type="string", format="date"),
     *                 @OA\Property(property="transactions", type="integer"),
     *                 @OA\Property(property="revenue", type="number", format="float"),
     *                 @OA\Property(property="avg_ticket", type="number", format="float"),
     *                 @OA\Property(property="cash_sales", type="number", format="float"),
     *                 @OA\Property(property="card_sales", type="number", format="float")
     *             )
     *         )
     *     )
     * )
     */
    public function getDailySales(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 30);
        
        $results = DB::select("
            SELECT * FROM v_daily_sales 
            LIMIT ?
        ", [$limit]);
        
        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/business-intelligence/monthly-sales",
     *     tags={"Business Intelligence"},
     *     summary="Ventas mensuales",
     *     description="Obtiene reporte de ventas agrupadas por mes",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Número de meses a mostrar (default: 12)",
     *         @OA\Schema(type="integer", default=12)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reporte de ventas mensuales",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="month", type="string", format="date"),
     *                 @OA\Property(property="transactions", type="integer"),
     *                 @OA\Property(property="revenue", type="number", format="float"),
     *                 @OA\Property(property="avg_ticket", type="number", format="float")
     *             )
     *         )
     *     )
     * )
     */
    public function getMonthlySales(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 12);
        
        $results = DB::select("
            SELECT * FROM v_monthly_sales 
            LIMIT ?
        ", [$limit]);
        
        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/business-intelligence/top-products",
     *     tags={"Business Intelligence"},
     *     summary="Top productos últimos 30 días",
     *     description="Obtiene los productos más vendidos en los últimos 30 días",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Top 10 productos más vendidos",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="total_sold", type="integer"),
     *                 @OA\Property(property="total_revenue", type="number", format="float"),
     *                 @OA\Property(property="times_sold", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function getTopProducts(): JsonResponse
    {
        $results = DB::select("SELECT * FROM v_top_products");
        
        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/business-intelligence/low-stock",
     *     tags={"Business Intelligence"},
     *     summary="Productos con stock bajo",
     *     description="Obtiene productos que están en stock bajo o sin stock",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Productos con stock bajo",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="stock", type="integer"),
     *                 @OA\Property(property="min_stock", type="integer"),
     *                 @OA\Property(property="category", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function getLowStock(): JsonResponse
    {
        $results = DB::select("SELECT * FROM v_low_stock");
        
        return response()->json($results);
    }

    /**
     * @OA\Get(
     *     path="/api/business-intelligence/dashboard",
     *     tags={"Business Intelligence"},
     *     summary="Dashboard principal",
     *     description="Obtiene datos resumidos para el dashboard principal",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Datos del dashboard",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="today_revenue", type="object"),
     *             @OA\Property(property="month_revenue", type="object"),
     *             @OA\Property(property="top_products", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="low_stock_count", type="integer"),
     *             @OA\Property(property="recent_sales", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function getDashboard(): JsonResponse
    {
        // Ingresos de hoy
        $todayRevenue = DB::select('SELECT * FROM get_revenue(?)', ['today'])[0] ?? null;
        
        // Ingresos del mes
        $monthRevenue = DB::select('SELECT * FROM get_revenue(?)', ['this_month'])[0] ?? null;
        
        // Top productos
        $topProducts = DB::select("SELECT * FROM v_top_products LIMIT 5");
        
        // Productos con stock bajo
        $lowStockCount = DB::select("SELECT COUNT(*) as count FROM v_low_stock")[0]->count ?? 0;
        
        // Ventas recientes (últimas 5)
        $recentSales = DB::select("
            SELECT s.id, s.sale_number, s.customer_name, s.total, s.sale_date, s.payment_method,
                   u.first_name || ' ' || u.last_name as sold_by_name
            FROM sales s
            JOIN users u ON s.sold_by = u.id
            ORDER BY s.created_at DESC
            LIMIT 5
        ");

        return response()->json([
            'today_revenue' => [
                'revenue' => (float) ($todayRevenue->revenue ?? 0),
                'transactions' => (int) ($todayRevenue->transactions ?? 0),
                'avg_ticket' => $todayRevenue && $todayRevenue->transactions > 0 
                    ? (float) $todayRevenue->revenue / $todayRevenue->transactions : 0
            ],
            'month_revenue' => [
                'revenue' => (float) ($monthRevenue->revenue ?? 0),
                'transactions' => (int) ($monthRevenue->transactions ?? 0),
                'avg_ticket' => $monthRevenue && $monthRevenue->transactions > 0 
                    ? (float) $monthRevenue->revenue / $monthRevenue->transactions : 0
            ],
            'top_products' => $topProducts,
            'low_stock_count' => (int) $lowStockCount,
            'recent_sales' => $recentSales
        ]);
    }
}
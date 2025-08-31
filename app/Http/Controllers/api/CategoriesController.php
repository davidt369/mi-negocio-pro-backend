<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoriesRequest;
use App\Http\Requests\UpdateCategoriesRequest;
use App\Models\Categories;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Bebidas"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2025-08-24T10:00:00.000000Z"),
 *     @OA\Property(property="active_products_count", type="integer", example=5)
 * )
 */
class CategoriesController extends Controller
{
    use AuthorizesRequests;

    /**
     * @OA\Get(
     *     path="/api/categories",
     *     summary="Listar categorías",
     *     description="Obtener lista de categorías con filtros opcionales",
     *     operationId="getCategoriesList",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filtrar por estado activo",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Buscar por nombre de categoría",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de categorías",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categorías obtenidas exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Categories::class);

        $query = Categories::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->search($request->search);
        }

        $categories = $query->orderBy('name')->get();

        // Add products count for each category
        $categories->each(function ($category) {
            $category->active_products_count = $category->activeProductsCount;
        });

        return response()->json([
            'success' => true,
            'message' => 'Categorías obtenidas exitosamente',
            'data' => $categories
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/categories",
     *     summary="Crear categoría",
     *     description="Crear nueva categoría de productos",
     *     operationId="createCategory",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Nueva Categoría"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Categoría creada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categoría creada exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(StoreCategoriesRequest $request): JsonResponse
    {
        $this->authorize('create', Categories::class);

        $category = Categories::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada exitosamente',
            'data' => $category
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     path="/api/categories/{id}",
     *     summary="Obtener categoría",
     *     description="Obtener detalles de una categoría específica",
     *     operationId="getCategory",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la categoría",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles de la categoría",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categoría obtenida exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Categoría no encontrada"
     *     )
     * )
     */
    public function show(Categories $category): JsonResponse
    {
        $this->authorize('view', $category);

        return response()->json([
            'success' => true,
            'message' => 'Categoría obtenida exitosamente',
            'data' => $category
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/categories/{id}",
     *     summary="Actualizar categoría",
     *     description="Actualizar información de una categoría existente",
     *     operationId="updateCategory",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la categoría",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Categoría Actualizada"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categoría actualizada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categoría actualizada exitosamente"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function update(UpdateCategoriesRequest $request, Categories $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada exitosamente',
            'data' => $category->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/categories/{id}",
     *     summary="Eliminar categoría",
     *     description="Eliminar una categoría del sistema",
     *     operationId="deleteCategory",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la categoría",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categoría eliminada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categoría eliminada exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No se puede eliminar categoría con productos asociados"
     *     )
     * )
     */
    public function destroy(Categories $category): JsonResponse
    {
        $this->authorize('delete', $category);

        // Check if category has products
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados'
            ], Response::HTTP_BAD_REQUEST);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada exitosamente'
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/categories/{id}/toggle-status",
     *     summary="Cambiar estado de categoría",
     *     description="Activar o desactivar una categoría",
     *     operationId="toggleCategoryStatus",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de la categoría",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado de categoría cambiado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categoría activada"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function toggleStatus(Categories $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update(['is_active' => !$category->is_active]);

        return response()->json([
            'success' => true,
            'message' => $category->is_active ? 'Categoría activada' : 'Categoría desactivada',
            'data' => $category
        ]);
    }
}

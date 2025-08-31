<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Schema(
 *     schema="Business",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Mi Negocio"),
 *     @OA\Property(property="owner_name", type="string", example="Propietario"),
 *     @OA\Property(property="phone", type="string", example="+57 300 123 4567"),
 *     @OA\Property(property="email", type="string", format="email", example="info@minegocio.com"),
 *     @OA\Property(property="address", type="string", example="Calle 123 #45-67"),
 *     @OA\Property(property="currency", type="string", example="COP"),
 *     @OA\Property(property="tax_rate", type="number", format="float", example=0.19),
 *     @OA\Property(property="created_at", type="string", format="datetime"),
 *     @OA\Property(property="updated_at", type="string", format="datetime")
 * )
 */

class BusinessController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/business",
     *     summary="Obtener información del negocio",
     *     description="Obtener la configuración e información del negocio",
     *     operationId="getBusiness",
     *     tags={"Business"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Información del negocio obtenida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Información del negocio obtenida exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="business", type="object"),
     *                 @OA\Property(property="tax_rate_percentage", type="number", example=19),
     *                 @OA\Property(property="currency_symbol", type="string", example="$")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error interno del servidor"
     *     )
     * )
     */
    public function show(): JsonResponse
    {
        try {
            $business = Business::getInstance();
            
            return response()->json([
                'success' => true,
                'message' => 'Información del negocio obtenida exitosamente',
                'data' => [
                    'business' => $business,
                    'tax_rate_percentage' => $business->tax_rate_percentage,
                    'currency_symbol' => $business->currency_symbol
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la información del negocio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/business",
     *     summary="Actualizar información del negocio",
     *     description="Actualizar toda la información del negocio",
     *     operationId="updateBusiness",
     *     tags={"Business"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Mi Negocio Actualizado"),
     *             @OA\Property(property="owner_name", type="string", example="Propietario Actualizado"),
     *             @OA\Property(property="phone", type="string", example="+57 300 123 4567"),
     *             @OA\Property(property="email", type="string", format="email", example="info@minegocio.com"),
     *             @OA\Property(property="address", type="string", example="Calle 123 #45-67"),
     *             @OA\Property(property="currency", type="string", example="COP"),
     *             @OA\Property(property="tax_rate", type="number", format="float", example=0.19)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Información actualizada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Información del negocio actualizada exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="business", type="object"),
     *                 @OA\Property(property="tax_rate_percentage", type="number"),
     *                 @OA\Property(property="currency_symbol", type="string")
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
    public function update(Request $request): JsonResponse
    {
        try {
            // Validar los datos
            $validated = $request->validate(Business::rules());

            // Obtener la instancia del negocio
            $business = Business::getInstance();

            // Actualizar los datos
            $business->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Información del negocio actualizada exitosamente',
                'data' => [
                    'business' => $business->fresh(),
                    'tax_rate_percentage' => $business->tax_rate_percentage,
                    'currency_symbol' => $business->currency_symbol
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la información del negocio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/business/summary",
     *     summary="Obtener resumen del negocio",
     *     description="Obtener resumen e información clave del negocio para dashboard",
     *     operationId="getBusinessSummary",
     *     tags={"Business"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Resumen obtenido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Resumen del negocio obtenido exitosamente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="business_info",
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Mi Negocio"),
     *                     @OA\Property(property="owner", type="string", example="Propietario"),
     *                     @OA\Property(property="currency", type="string", example="COP"),
     *                     @OA\Property(property="currency_symbol", type="string", example="$"),
     *                     @OA\Property(property="tax_rate", type="string", example="19%")
     *                 ),
     *                 @OA\Property(property="setup_completed", type="boolean", example=true),
     *                 @OA\Property(property="last_updated", type="string", example="2025-08-24 10:00:00")
     *             )
     *         )
     *     )
     * )
     */
    public function summary(): JsonResponse
    {
        try {
            $business = Business::getInstance();

            // Aquí puedes agregar más estadísticas del negocio
            $summary = [
                'business_info' => [
                    'name' => $business->name,
                    'owner' => $business->owner_name,
                    'currency' => $business->currency,
                    'currency_symbol' => $business->currency_symbol,
                    'tax_rate' => $business->tax_rate_percentage . '%'
                ],
                'setup_completed' => $this->isSetupCompleted($business),
                'last_updated' => $business->updated_at->format('Y-m-d H:i:s')
            ];

            return response()->json([
                'success' => true,
                'message' => 'Resumen del negocio obtenido exitosamente',
                'data' => $summary
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el resumen del negocio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if business setup is completed.
     */
    private function isSetupCompleted(Business $business): bool
    {
        return !empty($business->name) && 
               !empty($business->owner_name) && 
               !empty($business->phone) && 
               !empty($business->email);
    }

    /**
     * @OA\Patch(
     *     path="/api/business",
     *     summary="Actualización parcial del negocio",
     *     description="Actualizar solo campos específicos de la información del negocio",
     *     operationId="patchBusiness",
     *     tags={"Business"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Mi Negocio Actualizado"),
     *             @OA\Property(property="phone", type="string", example="+57 300 123 4567"),
     *             @OA\Property(property="email", type="string", format="email", example="info@minegocio.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Información actualizada parcialmente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Información del negocio actualizada parcialmente"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="business", type="object"),
     *                 @OA\Property(property="updated_fields", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function patch(Request $request): JsonResponse
    {
        try {
            $business = Business::getInstance();

            // Validar solo los campos enviados
            $rules = Business::rules();
            $validated = $request->validate(
                array_intersect_key($rules, $request->all())
            );

            // Actualizar solo los campos enviados
            $business->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Información del negocio actualizada parcialmente',
                'data' => [
                    'business' => $business->fresh(),
                    'updated_fields' => array_keys($validated)
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de validación incorrectos',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la información del negocio',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @OA\Info(
 *      version="1.0.0",
 *      title="Mi Negocio Pro API",
 *      description="API para gestión completa de negocio: usuarios, productos, ventas, compras y más",
 *      @OA\Contact(
 *          email="support@minegocio.com"
 *      ),
 *      @OA\License(
 *          name="MIT",
 *          url="https://opensource.org/licenses/MIT"
 *      )
 * )
 * 
 * @OA\Server(
 *      url="http://localhost:8000",
 *      description="API Server Local"
 * )
 * 
 * @OA\SecurityScheme(
 *      securityScheme="sanctum",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 *      description="Laravel Sanctum token authentication"
 * )
 * 
 * @OA\Schema(
 *     schema="LoginRequest",
 *     type="object",
 *     required={"email", "password"},
 *     @OA\Property(property="email", type="string", format="email", example="usuario@ejemplo.com"),
 *     @OA\Property(property="password", type="string", format="password", example="password123")
 * )
 * 
 * @OA\Schema(
 *     schema="LoginResponse",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="Inicio de sesión exitoso"),
 *     @OA\Property(property="user", ref="#/components/schemas/UserResponse"),
 *     @OA\Property(property="token", type="string", example="1|abcdef123456...")
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="Autenticación y gestión de sesiones"
 * )
 * 
 * @OA\Tag(
 *     name="Users",
 *     description="Gestión de usuarios (propietarios y empleados)"
 * )
 * 
 * @OA\Tag(
 *     name="Business",
 *     description="Configuración e información del negocio"
 * )
 */

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     summary="Iniciar sesión",
     *     description="Autenticar usuario y obtener token de acceso. Los usuarios inactivos no podrán autenticarse.",
     *     operationId="login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Credenciales de autenticación",
     *         @OA\JsonContent(ref="#/components/schemas/LoginRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Inicio de sesión exitoso",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error de validación"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="El email es requerido")
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="array",
     *                     @OA\Items(type="string", example="La contraseña es requerida")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Credenciales inválidas",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Las credenciales proporcionadas son incorrectas.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Cuenta inactiva",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Tu cuenta está inactiva. Contacta al administrador.")
     *         )
     *     )
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'El email es requerido',
            'email.email' => 'El email debe tener un formato válido',
            'password.required' => 'La contraseña es requerida',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (!$user->active) {
            throw ValidationException::withMessages([
                'email' => ['Tu cuenta está inactiva. Contacta al administrador.'],
            ]);
        }

        // Revoke all existing tokens
        $user->tokens()->delete();

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     summary="Cerrar sesión",
     *     description="Cerrar sesión actual y revocar token",
     *     operationId="logout",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sesión cerrada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sesión cerrada exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Get current token and delete it safely
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $currentToken */
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout-all",
     *     summary="Cerrar todas las sesiones",
     *     description="Cerrar sesión en todos los dispositivos",
     *     operationId="logoutAll",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sesiones cerradas exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sesión cerrada en todos los dispositivos")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Sesión cerrada en todos los dispositivos'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     summary="Obtener usuario actual",
     *     description="Obtener información del usuario autenticado",
     *     operationId="me",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuario actual",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     summary="Renovar token",
     *     description="Renovar token de autenticación",
     *     operationId="refresh",
     *     tags={"Authentication"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token renovado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Token renovado exitosamente"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string", example="1|xyz789...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Delete current token safely
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $currentToken */
        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        // Create new token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Token renovado exitosamente',
            'user' => $user,
            'token' => $token,
        ]);
    }
}

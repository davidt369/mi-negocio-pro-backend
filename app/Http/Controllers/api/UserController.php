<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     required={"first_name", "last_name", "email", "password", "role"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1, readOnly=true),
 *     @OA\Property(property="first_name", type="string", example="Juan"),
 *     @OA\Property(property="last_name", type="string", example="Pérez"),
 *     @OA\Property(property="email", type="string", format="email", example="usuario@ejemplo.com"),
 *     @OA\Property(property="phone", type="string", nullable=true, example="+1234567890"),
 *     @OA\Property(property="role", type="string", enum={"owner", "employee"}, example="employee"),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="business_name", type="string", nullable=true, example="Mi Negocio"),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true, example="2025-08-24T10:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true, example="2025-08-24T10:00:00Z")
 * )
 *
 * @OA\Schema(
 *     schema="UserResponse",
 *     type="object",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/User"),
 *         @OA\Schema(
 *             type="object",
 *             @OA\Property(property="full_name", type="string", example="Juan Pérez", readOnly=true, description="Nombre completo generado a partir de first_name y last_name")
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="ValidationError",
 *     type="object",
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(
 *         property="errors",
 *         type="object",
 *         @OA\Property(
 *             property="field_name",
 *             type="array",
 *             @OA\Items(type="string", example="The field is required.")
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="PaginatedResponse",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", example=1),
 *     @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="first_page_url", type="string", example="http://localhost/api/users?page=1"),
 *     @OA\Property(property="from", type="integer", example=1),
 *     @OA\Property(property="last_page", type="integer", example=10),
 *     @OA\Property(property="last_page_url", type="string", example="http://localhost/api/users?page=10"),
 *     @OA\Property(property="links", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="next_page_url", type="string", example="http://localhost/api/users?page=2"),
 *     @OA\Property(property="path", type="string", example="http://localhost/api/users"),
 *     @OA\Property(property="per_page", type="integer", example=15),
 *     @OA\Property(property="prev_page_url", type="string", example=null),
 *     @OA\Property(property="to", type="integer", example=15),
 *     @OA\Property(property="total", type="integer", example=150)
 * )
 */

class UserController extends Controller
{
    use AuthorizesRequests;
    /**
     * @OA\Get(
     *     path="/api/users",
     *     summary="Listar usuarios",
     *     description="Obtener lista paginada de usuarios. Solo usuarios con rol 'owner' pueden ver la lista de usuarios.",
     *     operationId="getUsersList",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="role",
     *         in="query",
     *         description="Filtrar por rol",
     *         required=false,
     *         @OA\Schema(type="string", enum={"owner", "employee"})
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filtrar por estado activo",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Buscar por nombre o email",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Elementos por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de usuarios",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UserResponse")),
     *             @OA\Property(property="links", type="object"),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No tienes permiso para realizar esta acción")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query();

        // Filter by role
        if ($request->has('role')) {
            $query->byRole($request->role);
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($users);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Crear nuevo usuario",
     *     description="Crear un nuevo usuario en el sistema. Solo usuarios con rol 'owner' pueden crear usuarios.",
     *     operationId="createUser",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del usuario a crear",
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "email", "password", "role"},
     *             @OA\Property(property="first_name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="email", type="string", format="email", example="usuario@ejemplo.com"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+1234567890"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="role", type="string", enum={"owner", "employee"}, example="employee"),
     *             @OA\Property(property="active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Usuario creado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Usuario creado exitosamente"),
     *             @OA\Property(property="data", ref="#/components/schemas/UserResponse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No tienes permiso para realizar esta acción")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'data' => $user
        ], Response::HTTP_CREATED);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Obtener usuario",
     *     description="Obtener detalles de un usuario específico. Los usuarios solo pueden ver su propia información a menos que tengan rol 'owner'.",
     *     operationId="getUser",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del usuario",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del usuario",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/UserResponse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No tienes permiso para ver este usuario")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No se encontró el usuario especificado")
     *         )
     *     )
     * )
     */
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => $user
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="Actualizar usuario",
     *     description="Actualizar información de un usuario existente. Los usuarios solo pueden actualizar su propia información a menos que tengan rol 'owner'.",
     *     operationId="updateUser",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del usuario a actualizar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del usuario a actualizar",
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="email", type="string", format="email", example="usuario@ejemplo.com"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+1234567890"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="role", type="string", enum={"owner", "employee"}, example="employee"),
     *             @OA\Property(property="active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Usuario actualizado exitosamente"),
     *             @OA\Property(property="data", ref="#/components/schemas/UserResponse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No tienes permiso para actualizar este usuario")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No se encontró el usuario especificado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        // Only hash password if it's being updated
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'data' => $user->fresh()
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     summary="Eliminar usuario",
     *     description="Eliminar un usuario del sistema. Solo usuarios con rol 'owner' pueden eliminar usuarios. No se puede eliminar el último propietario del sistema.",
     *     operationId="deleteUser",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID del usuario a eliminar",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario eliminado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Usuario eliminado exitosamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No tienes permiso para eliminar usuarios")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No se encontró el usuario especificado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="No se puede eliminar al último propietario",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No se puede eliminar al último propietario del sistema")
     *         )
     *     )
     * )
     */
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        // Prevent deletion of owner
        if ($user->isOwner()) {
            return response()->json([
                'message' => 'No se puede eliminar al propietario'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Toggle user active status.
     */
    public function toggleStatus(User $user): JsonResponse
    {
        $this->authorize('toggleStatus', $user);

        // Prevent deactivating owner
        if ($user->isOwner() && $user->active) {
            return response()->json([
                'message' => 'No se puede desactivar al propietario'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->update(['active' => !$user->active]);

        return response()->json([
            'message' => $user->fresh()->active ? 'Usuario activado' : 'Usuario desactivado',
            'data' => $user->fresh()
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/profile",
     *     summary="Obtener perfil actual",
     *     description="Obtener información del perfil del usuario autenticado",
     *     operationId="getUserProfile",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Perfil del usuario",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/UserResponse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No autenticado")
     *         )
     *     )
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/users/profile",
     *     summary="Actualizar perfil actual",
     *     description="Actualizar la información del perfil del usuario autenticado",
     *     operationId="updateUserProfile",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Datos del perfil a actualizar",
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="phone", type="string", nullable=true, example="+1234567890"),
     *             @OA\Property(property="email", type="string", format="email", example="usuario@ejemplo.com"),
     *             @OA\Property(property="current_password", type="string", format="password", example="currentpassword123"),
     *             @OA\Property(property="password", type="string", format="password", example="newpassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Perfil actualizado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Perfil actualizado exitosamente"),
     *             @OA\Property(property="data", ref="#/components/schemas/UserResponse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No autenticado")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     )
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:50',
            'last_name' => 'sometimes|string|max:50',
            'phone' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|email|max:100|unique:users,email,' . $user->id,
            'current_password' => ['required_with:password', function ($attribute, $value, $fail) use ($user) {
                if (!Hash::check($value, $user->password)) {
                    $fail('La contraseña actual es incorrecta');
                }
            }],
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        // Remove current_password and password_confirmation from update data
        unset($validated['current_password'], $validated['password_confirmation']);

        // Hash new password if provided
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'data' => $user->fresh()
        ]);
    }
}

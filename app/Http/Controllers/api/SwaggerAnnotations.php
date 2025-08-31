<?php

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
 *      url=L5_SWAGGER_CONST_HOST,
 *      description="API Server"
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
 * 
 * @OA\Tag(
 *     name="Categories",
 *     description="Gestión de categorías de productos"
 * )
 * 
 * @OA\Tag(
 *     name="Products",
 *     description="Gestión de productos del inventario"
 * )
 * 
 * @OA\Tag(
 *     name="Sales",
 *     description="Gestión de ventas y facturación"
 * )
 * 
 * @OA\Tag(
 *     name="Purchases",
 *     description="Gestión de compras y proveedores"
 * )
 * 
 * // Common Components
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="email", type="string", format="email", example="admin@minegocio.com"),
 *     @OA\Property(property="full_name", type="string", example="Administrador Principal"),
 *     @OA\Property(property="phone", type="string", example="+57 300 123 4567"),
 *     @OA\Property(property="role", type="string", enum={"owner", "employee"}, example="owner"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="datetime", example="2025-08-24T10:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="datetime", example="2025-08-24T10:00:00.000000Z")
 * )
 * 
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
 * 
 * @OA\Schema(
 *     schema="Error",
 *     type="object",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Error message"),
 *     @OA\Property(property="errors", type="object", additionalProperties={"type": "array", "items": {"type": "string"}})
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
 * 
 * @OA\Schema(
 *     schema="SaleItem",
 *     type="object",
 *     title="Sale Item",
 *     description="Item de venta - detalle de productos vendidos",
 *     required={"sale_id", "product_id", "quantity", "unit_price"},
 *     @OA\Property(property="id", type="integer", format="int64", description="ID único del item de venta"),
 *     @OA\Property(property="sale_id", type="integer", format="int64", description="ID de la venta"),
 *     @OA\Property(property="product_id", type="integer", format="int64", description="ID del producto"),
 *     @OA\Property(property="quantity", type="integer", minimum=1, description="Cantidad vendida"),
 *     @OA\Property(property="unit_price", type="number", format="decimal", minimum=0, description="Precio unitario"),
 *     @OA\Property(property="line_total", type="number", format="decimal", description="Total de la línea (calculado automáticamente)"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Fecha de creación"),
 *     @OA\Property(property="product_name", type="string", description="Nombre del producto (campo calculado)"),
 *     @OA\Property(property="total_formatted", type="string", description="Total formateado (campo calculado)"),
 *     @OA\Property(property="unit_price_formatted", type="string", description="Precio unitario formateado (campo calculado)"),
 *     @OA\Property(property="sale", ref="#/components/schemas/Sale", description="Venta asociada"),
 *     @OA\Property(property="product", ref="#/components/schemas/Product", description="Producto vendido")
 * )
 * 
 * @OA\Schema(
 *     schema="Purchase",
 *     type="object",
 *     title="Purchase",
 *     description="Compra/Entrada de inventario",
 *     required={"received_by"},
 *     @OA\Property(property="id", type="integer", format="int64", description="ID único de la compra"),
 *     @OA\Property(property="supplier_name", type="string", maxLength=100, description="Nombre del proveedor"),
 *     @OA\Property(property="total", type="number", format="decimal", minimum=0, description="Total de la compra"),
 *     @OA\Property(property="notes", type="string", description="Notas adicionales"),
 *     @OA\Property(property="purchase_date", type="string", format="date", description="Fecha de la compra"),
 *     @OA\Property(property="received_by", type="integer", format="int64", description="ID del usuario que recibió"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Fecha de creación"),
 *     @OA\Property(property="user_name", type="string", description="Nombre del usuario que recibió (campo calculado)"),
 *     @OA\Property(property="total_formatted", type="string", description="Total formateado (campo calculado)"),
 *     @OA\Property(property="purchase_date_formatted", type="string", description="Fecha formateada (campo calculado)"),
 *     @OA\Property(property="items_count", type="integer", description="Número de items (campo calculado)"),
 *     @OA\Property(property="total_quantity", type="integer", description="Cantidad total de productos (campo calculado)"),
 *     @OA\Property(property="user", ref="#/components/schemas/User", description="Usuario que recibió la compra"),
 *     @OA\Property(property="items", type="array", @OA\Items(ref="#/components/schemas/PurchaseItems"), description="Items de la compra")
 * )
 */
# Funcionalidad de Imágenes para Productos

## Cambios Realizados

### ✅ Eliminado
- Campo `barcode` de la tabla products
- Validaciones relacionadas con código de barras
- Búsqueda por código de barras

### ✅ Agregado
- Campo `image_path` para almacenar la ruta de la imagen
- Subida de imágenes en formatos: jpeg, png, jpg, gif (máximo 2MB)
- Eliminación automática de imágenes al actualizar o eliminar productos
- Endpoint para eliminar imágenes específicamente

## Endpoints Disponibles

### 1. Crear Producto con Imagen
```
POST /api/products
Content-Type: multipart/form-data

Campos:
- name: string (requerido)
- image: file (opcional, formatos: jpeg,png,jpg,gif, max: 2MB)
- category_id: integer (opcional)
- cost_price: decimal (opcional)
- sale_price: decimal (requerido)
- stock: integer (opcional, default: 0)
- min_stock: integer (opcional, default: 5)
- is_active: boolean (opcional, default: true)
```

### 2. Actualizar Producto con Imagen
```
PUT /api/products/{id}
Content-Type: multipart/form-data

Campos: (todos opcionales)
- name: string
- image: file (si se envía, reemplaza la imagen actual)
- category_id: integer
- cost_price: decimal
- sale_price: decimal
- stock: integer
- min_stock: integer
- is_active: boolean
```

### 3. Eliminar Solo la Imagen
```
DELETE /api/products/{id}/image
```

### 4. Obtener Producto (incluye URL de imagen)
```
GET /api/products/{id}

Respuesta incluye:
- image_path: ruta relativa de la imagen
- image_url: URL completa para acceder a la imagen
```

## Ejemplos de Uso

### JavaScript/Fetch
```javascript
// Crear producto con imagen
const formData = new FormData();
formData.append('name', 'Coca Cola 600ml');
formData.append('sale_price', '20.00');
formData.append('image', fileInput.files[0]); // archivo seleccionado

fetch('/api/products', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Accept': 'application/json'
    },
    body: formData
});

// Actualizar solo la imagen
const imageFormData = new FormData();
imageFormData.append('image', newImageFile);

fetch('/api/products/1', {
    method: 'PUT',
    headers: {
        'Authorization': 'Bearer ' + token,
        'Accept': 'application/json'
    },
    body: imageFormData
});
```

### PHP/cURL
```php
// Crear producto con imagen
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/products');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'name' => 'Producto Test',
    'sale_price' => '15.50',
    'image' => new CURLFile('/path/to/image.jpg', 'image/jpeg', 'producto.jpg')
]);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
$response = curl_exec($ch);
curl_close($ch);
```

## Estructura de Respuesta

### Producto con Imagen
```json
{
    "success": true,
    "message": "Producto creado exitosamente",
    "data": {
        "id": 1,
        "name": "Coca Cola 600ml",
        "image_path": "products/abc123.jpg",
        "image_url": "http://localhost:8000/storage/products/abc123.jpg",
        "category_id": 1,
        "cost_price": "15.50",
        "sale_price": "20.00",
        "stock": 100,
        "min_stock": 10,
        "is_active": true,
        "created_at": "2025-08-24T22:54:00.000000Z",
        "updated_at": "2025-08-24T22:54:00.000000Z",
        "profit_margin": 29.03,
        "profit_amount": 4.50,
        "is_low_stock": false,
        "stock_status": "in_stock",
        "category": {
            "id": 1,
            "name": "Bebidas"
        }
    }
}
```

## Notas Importantes

1. **Formato de Datos**: Para subir imágenes, usar `multipart/form-data` en lugar de JSON
2. **Tamaño Máximo**: Las imágenes no pueden exceder 2MB
3. **Formatos Soportados**: jpeg, png, jpg, gif
4. **Gestión Automática**: Las imágenes se eliminan automáticamente al actualizar o eliminar productos
5. **Almacenamiento**: Las imágenes se guardan en `storage/app/public/products/`
6. **Acceso Público**: Las imágenes son accesibles vía la URL generada en `image_url`

## Validaciones

### Crear Producto
- `name`: requerido, máximo 100 caracteres
- `image`: opcional, debe ser imagen válida, máximo 2MB
- `sale_price`: requerido, numérico, mínimo 0

### Actualizar Producto
- Todos los campos son opcionales
- `image`: si se envía, debe ser imagen válida, máximo 2MB
- La imagen anterior se elimina automáticamente al subir una nueva
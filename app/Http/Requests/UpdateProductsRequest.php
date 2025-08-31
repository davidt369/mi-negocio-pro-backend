<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateProductRequest",
 *     type="object",
 *     @OA\Property(property="name", type="string", maxLength=100, description="Product name", example="Coca Cola 600ml"),
 *     @OA\Property(property="image", type="string", format="binary", description="Product image"),
 *     @OA\Property(property="category_id", type="integer", description="Category ID", example=1),
 *     @OA\Property(property="cost_price", type="number", format="float", description="Cost price", example=15.50),
 *     @OA\Property(property="sale_price", type="number", format="float", description="Sale price", example=20.00),
 *     @OA\Property(property="stock", type="integer", description="Current stock", example=100),
 *     @OA\Property(property="min_stock", type="integer", description="Minimum stock alert", example=10),
 *     @OA\Property(property="is_active", type="boolean", description="Product status", example=true)
 * )
 */
class UpdateProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:100',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category_id' => 'nullable|exists:categories,id',
            'cost_price' => 'nullable|numeric|min:0|max:999999.99',
            'sale_price' => 'sometimes|required|numeric|min:0|max:999999.99',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del producto es obligatorio.',
            'image.image' => 'El archivo debe ser una imagen.',
            'image.mimes' => 'La imagen debe ser de tipo: jpeg, png, jpg, gif.',
            'image.max' => 'La imagen no puede ser mayor a 2MB.',
            'category_id.exists' => 'La categoría seleccionada no existe.',
            'sale_price.required' => 'El precio de venta es obligatorio.',
            'sale_price.numeric' => 'El precio de venta debe ser un número.',
        ];
    }
}

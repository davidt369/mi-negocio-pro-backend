<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_id' => [
                'required',
                'integer',
                Rule::exists('purchases', 'id')
            ],
            'product_id' => [
                'required',
                'integer', 
                Rule::exists('products', 'id')->where(function ($query) {
                    return $query->where('is_active', true);
                })
            ],
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:999999'
            ],
            'unit_cost' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99'
            ]
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_id.required' => 'El ID de la compra es obligatorio.',
            'purchase_id.integer' => 'El ID de la compra debe ser un número entero.',
            'purchase_id.exists' => 'La compra seleccionada no existe.',
            
            'product_id.required' => 'El ID del producto es obligatorio.',
            'product_id.integer' => 'El ID del producto debe ser un número entero.',
            'product_id.exists' => 'El producto seleccionado no existe o está inactivo.',
            
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser mayor a 0.',
            'quantity.max' => 'La cantidad no puede exceder 999,999 unidades.',
            
            'unit_cost.required' => 'El costo unitario es obligatorio.',
            'unit_cost.numeric' => 'El costo unitario debe ser un valor numérico.',
            'unit_cost.min' => 'El costo unitario no puede ser negativo.',
            'unit_cost.max' => 'El costo unitario no puede exceder $999,999.99.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purchase_id' => 'compra',
            'product_id' => 'producto',
            'quantity' => 'cantidad',
            'unit_cost' => 'costo unitario'
        ];
    }
}
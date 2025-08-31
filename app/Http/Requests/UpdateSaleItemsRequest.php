<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSaleItemsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => [
                'required',
                'integer',
                'min:1',
                'max:99999',
            ],
            'unit_price' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
                'regex:/^\d+(\.\d{1,2})?$/', // Máximo 2 decimales
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.integer' => 'La cantidad debe ser un número entero.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'quantity.max' => 'La cantidad no puede exceder 99,999 unidades.',
            
            'unit_price.required' => 'El precio unitario es obligatorio.',
            'unit_price.numeric' => 'El precio unitario debe ser un número.',
            'unit_price.min' => 'El precio unitario no puede ser negativo.',
            'unit_price.max' => 'El precio unitario no puede exceder $999,999.99.',
            'unit_price.regex' => 'El precio unitario debe tener máximo 2 decimales.',
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
            'quantity' => 'cantidad',
            'unit_price' => 'precio unitario',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Asegurar que los valores numéricos estén en el formato correcto
        if ($this->has('unit_price')) {
            $this->merge([
                'unit_price' => number_format((float) $this->unit_price, 2, '.', ''),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Obtener el item de venta que se está actualizando
            $saleItem = $this->route('saleItem');
            
            if ($saleItem && $this->has('quantity')) {
                $currentQuantity = $saleItem->quantity;
                $newQuantity = $this->quantity;
                $quantityDifference = $newQuantity - $currentQuantity;
                
                // Solo validar stock si se aumenta la cantidad
                if ($quantityDifference > 0) {
                    $product = $saleItem->product;
                    
                    if ($product && $product->stock < $quantityDifference) {
                        $validator->errors()->add('quantity', 
                            "Stock insuficiente para aumentar la cantidad. Disponible: {$product->stock}, adicional necesario: {$quantityDifference}");
                    }
                }
            }
            
            // Validación adicional: verificar que el producto sigue activo
            if ($saleItem && $saleItem->product && !$saleItem->product->is_active) {
                $validator->errors()->add('quantity', 'No se puede modificar un item cuyo producto está inactivo.');
            }
        });
    }
}
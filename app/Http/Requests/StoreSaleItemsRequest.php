<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleItemsRequest extends FormRequest
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
            'sale_id' => [
                'required',
                'integer',
                Rule::exists('sales', 'id')->where(function ($query) {
                    $query->whereNull('deleted_at');
                }),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                }),
            ],
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
            'sale_id.required' => 'El ID de la venta es obligatorio.',
            'sale_id.integer' => 'El ID de la venta debe ser un número entero.',
            'sale_id.exists' => 'La venta especificada no existe o fue eliminada.',
            
            'product_id.required' => 'El ID del producto es obligatorio.',
            'product_id.integer' => 'El ID del producto debe ser un número entero.',
            'product_id.exists' => 'El producto especificado no existe o está inactivo.',
            
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
            'sale_id' => 'venta',
            'product_id' => 'producto',
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
            // Validación personalizada: verificar que el producto existe y está activo
            if ($this->has('product_id')) {
                $product = \App\Models\Products::find($this->product_id);
                
                if ($product && !$product->is_active) {
                    $validator->errors()->add('product_id', 'El producto seleccionado no está activo.');
                }
                
                // Validación de stock disponible
                if ($product && $this->has('quantity')) {
                    if ($product->stock < $this->quantity) {
                        $validator->errors()->add('quantity', 
                            "Stock insuficiente. Disponible: {$product->stock}, solicitado: {$this->quantity}");
                    }
                }
            }

            // Validación personalizada: verificar que la venta no esté finalizada
            if ($this->has('sale_id')) {
                $sale = \App\Models\Sales::find($this->sale_id);
                
                if ($sale) {
                    // Si implementas estados de venta en el futuro, aquí puedes validar
                    // Por ejemplo: if ($sale->status === 'finalized')
                }
            }
        });
    }
}
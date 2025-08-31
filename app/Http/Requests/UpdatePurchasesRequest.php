<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchasesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controlador
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_name' => 'sometimes|nullable|string|max:100',
            'notes' => 'sometimes|nullable|string|max:1000',
            'purchase_date' => 'sometimes|nullable|date|before_or_equal:today',
            'received_by' => 'sometimes|required|integer|exists:users,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'supplier_name.max' => 'El nombre del proveedor no puede superar los 100 caracteres.',
            'notes.max' => 'Las notas no pueden superar los 1000 caracteres.',
            'purchase_date.date' => 'La fecha de compra debe ser una fecha válida.',
            'purchase_date.before_or_equal' => 'La fecha de compra no puede ser futura.',
            'received_by.required' => 'El usuario que recibió la compra es obligatorio.',
            'received_by.integer' => 'El usuario que recibió la compra debe ser un número entero.',
            'received_by.exists' => 'El usuario seleccionado no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'supplier_name' => 'nombre del proveedor',
            'notes' => 'notas',
            'purchase_date' => 'fecha de compra',
            'received_by' => 'usuario que recibió',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Limpiar y formatear el nombre del proveedor
        if ($this->has('supplier_name') && $this->supplier_name) {
            $this->merge([
                'supplier_name' => trim($this->supplier_name),
            ]);
        }

        // Limpiar las notas
        if ($this->has('notes') && $this->notes) {
            $this->merge([
                'notes' => trim($this->notes),
            ]);
        }
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateSaleRequest",
    type: "object",
    title: "Update Sale Request",
    description: "Datos para actualizar una venta existente",
    properties: [
        new OA\Property(property: "customer_name", type: "string", maxLength: 100, description: "Nombre del cliente (opcional)"),
        new OA\Property(property: "total", type: "number", format: "decimal", minimum: 0, description: "Total de la venta"),
        new OA\Property(property: "payment_method", type: "string", enum: ["cash", "card", "transfer", "credit"], description: "Método de pago"),
        new OA\Property(property: "notes", type: "string", description: "Notas adicionales (opcional)"),
        new OA\Property(property: "sale_date", type: "string", format: "date", description: "Fecha de la venta"),
        new OA\Property(property: "sold_by", type: "integer", description: "ID del usuario que realiza la venta"),
    ]
)]
class UpdateSalesRequest extends FormRequest
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
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'total' => ['sometimes', 'required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'payment_method' => ['sometimes', 'required', 'string', 'in:cash,card,transfer,credit'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'sale_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'sold_by' => ['sometimes', 'required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'customer_name.max' => 'El nombre del cliente no puede exceder 100 caracteres.',
            'total.numeric' => 'El total debe ser un número válido.',
            'total.min' => 'El total no puede ser negativo.',
            'total.regex' => 'El total debe tener máximo 2 decimales.',
            'payment_method.in' => 'El método de pago debe ser: efectivo, tarjeta, transferencia o crédito.',
            'sale_date.date' => 'La fecha de venta debe ser una fecha válida.',
            'sale_date.before_or_equal' => 'La fecha de venta no puede ser futura.',
            'sold_by.exists' => 'El vendedor seleccionado no existe.',
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     */
    public function attributes(): array
    {
        return [
            'customer_name' => 'nombre del cliente',
            'total' => 'total',
            'payment_method' => 'método de pago',
            'notes' => 'notas',
            'sale_date' => 'fecha de venta',
            'sold_by' => 'vendedor',
        ];
    }
}

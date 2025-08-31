<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Will be handled by middleware and policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categoryId = $this->route('category')->id ?? null;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($categoryId)
            ],
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'name.string' => 'El nombre debe ser un texto válido',
            'name.max' => 'El nombre no puede exceder 100 caracteres',
            'name.unique' => 'Ya existe una categoría con este nombre',
            'is_active.boolean' => 'El estado debe ser verdadero o falso',
        ];
    }
}

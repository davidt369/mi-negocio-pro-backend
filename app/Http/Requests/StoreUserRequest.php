<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        return [
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'email' => ['required', 'email:rfc,dns', 'max:100', 'unique:users,email', 'regex:/@minegociopro\.com$/i'],
            'phone' => 'nullable|string|min:8|max:20|regex:/^\d{8,}$/',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(['owner', 'employee'])],
            'active' => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es requerido',
            'first_name.max' => 'El nombre no puede exceder 50 caracteres',
            'last_name.required' => 'El apellido es requerido',
            'last_name.max' => 'El apellido no puede exceder 50 caracteres',
            'email.required' => 'El email es requerido',
            'email.unique' => 'El email ya está registrado',
            'email.email' => 'El email debe tener un formato válido',
            'email.max' => 'El email no puede exceder 100 caracteres',
            'email.regex' => 'El correo debe pertenecer al dominio @minegociopro.com',
            'phone.min' => 'El teléfono debe tener al menos 8 dígitos',
            'phone.max' => 'El teléfono no puede exceder 20 caracteres',
            'phone.regex' => 'El teléfono solo debe contener números',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'role.required' => 'El rol es requerido',
            'role.in' => 'El rol debe ser owner o employee',
        ];
    }
}
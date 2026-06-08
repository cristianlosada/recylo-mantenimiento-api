<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('permissions')->ignore($this->route('id'))
            ],
            'description' => 'nullable|string',
            'module_id' => 'required|integer|exists:modules,id'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'code.required' => 'El código es requerido',
            'code.unique' => 'Este código ya está registrado',
            'module_id.required' => 'El módulo es requerido',
            'module_id.exists' => 'El módulo seleccionado no existe'
        ];
    }
}
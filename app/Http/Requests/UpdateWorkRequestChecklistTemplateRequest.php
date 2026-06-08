<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkRequestChecklistTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Manejar autorización con middleware/policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string', 'max:1000'],
            'asset_category_id' => ['nullable', 'integer', 'exists:asset_categories,id'],
            'request_type' => ['nullable', 'string', Rule::in(['corrective', 'preventive', 'improvement', 'inspection'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'is_active' => ['nullable', 'boolean'],
            'is_mandatory' => ['nullable', 'boolean'],
            'display_order' => ['nullable', 'integer', 'min:0'],
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
            'name.required' => 'El nombre de la plantilla es obligatorio',
            'name.max' => 'El nombre no puede exceder 255 caracteres',
            'description.required' => 'La descripción es obligatoria',
            'description.max' => 'La descripción no puede exceder 1000 caracteres',
            'asset_category_id.exists' => 'La categoría de activo seleccionada no existe',
            'request_type.in' => 'El tipo de solicitud debe ser: corrective, preventive, improvement o inspection',
            'priority.in' => 'La prioridad debe ser: low, medium, high o urgent',
            'is_active.boolean' => 'El estado activo debe ser verdadero o falso',
            'is_mandatory.boolean' => 'El campo obligatorio debe ser verdadero o falso',
            'display_order.integer' => 'El orden de visualización debe ser un número entero',
            'display_order.min' => 'El orden de visualización debe ser mayor o igual a 0',
        ];
    }
}

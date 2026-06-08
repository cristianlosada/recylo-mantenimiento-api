<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class UpdateWorkRequestRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para hacer esta petición
     */
    public function authorize(): bool
    {
        // La autorización se maneja en el middleware de permisos
        return true;
    }

    /**
     * Reglas de validación para actualizar solicitud de trabajo
     */
    public function rules(): array
    {
        return [
            // Campos opcionales (solo se actualizan si vienen en el request)
            'asset_id' => 'sometimes|required|integer|exists:assets,id',
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'request_type' => 'sometimes|required|in:corrective,preventive,improvement,inspection',
            'priority' => 'sometimes|required|in:low,medium,high,critical',
            'estimated_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'estimated_hours' => 'nullable|numeric|min:0|max:99999.99',
            
            // Archivos adjuntos adicionales
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
            
            // Etiquetas (reemplaza las existentes)
            'tags' => 'nullable|array',
            'tags.*' => 'integer|exists:work_request_tags,id',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'asset_id.exists' => 'El activo especificado no existe',
            'title.max' => 'El título no puede exceder 255 caracteres',
            'request_type.in' => 'El tipo de solicitud debe ser: corrective, preventive, improvement o inspection',
            'priority.in' => 'La prioridad debe ser: low, medium, high o critical',
            'estimated_cost.numeric' => 'El costo estimado debe ser numérico',
            'estimated_cost.min' => 'El costo estimado no puede ser negativo',
            'estimated_hours.numeric' => 'Las horas estimadas deben ser numéricas',
            'estimated_hours.min' => 'Las horas estimadas no pueden ser negativas',
            'attachments.max' => 'No se pueden adjuntar más de 10 archivos',
            'attachments.*.mimes' => 'Los archivos deben ser: jpg, jpeg, png, pdf, doc, docx, xls, xlsx',
            'attachments.*.max' => 'Cada archivo no puede exceder 10MB',
            'tags.*.exists' => 'Una o más etiquetas no existen',
        ];
    }

    /**
     * Manejar validación fallida
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }
}

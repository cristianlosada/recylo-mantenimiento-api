<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class StoreWorkRequestRequest extends FormRequest
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
     * Reglas de validación para crear solicitud de trabajo
     */
    public function rules(): array
    {
        return [
            // Campos requeridos
            'asset_id'     => 'required|integer|exists:assets,id',
            'description'  => 'required|string',
            'request_type' => 'required|in:corrective,preventive,improvement,inspection',
            'priority'     => 'required|in:low,medium,high,critical',

            // Título opcional — se auto-genera a partir de la descripción
            'title' => 'nullable|string|max:255',

            // Estado del equipo (HU-S1)
            'equipment_status' => 'nullable|in:operating_restricted,full_stop',

            // Solicitante (puede ser un usuario diferente al autenticado — para planificadores)
            'requester_id' => 'required|integer|exists:users,id',

            // Archivos adjuntos (múltiples)
            'attachments'   => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,webp,heic,heif,doc,docx,xls,xlsx|max:10240',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'asset_id.required' => 'El activo es requerido',
            'asset_id.exists' => 'El activo especificado no existe',
            'title.required' => 'El título es requerido',
            'title.max' => 'El título no puede exceder 255 caracteres',
            'description.required' => 'La descripción es requerida',
            'request_type.required' => 'El tipo de solicitud es requerido',
            'request_type.in' => 'El tipo de solicitud debe ser: corrective, preventive, improvement o inspection',
            'priority.required' => 'La prioridad es requerida',
            'priority.in' => 'La prioridad debe ser: low, medium, high o critical',
            'estimated_cost.numeric' => 'El costo estimado debe ser numérico',
            'estimated_cost.min' => 'El costo estimado no puede ser negativo',
            'estimated_hours.numeric' => 'Las horas estimadas deben ser numéricas',
            'estimated_hours.min' => 'Las horas estimadas no pueden ser negativas',
            'attachments.array' => 'Los archivos deben enviarse como un arreglo',
            'attachments.max' => 'No se pueden adjuntar más de 10 archivos',
            'attachments.*.mimes' => 'Los archivos deben ser: jpg, jpeg, png, pdf, doc, docx, xls, xlsx',
            'attachments.*.max' => 'Cada archivo no puede exceder 10MB',
            'tags.array' => 'Las etiquetas deben enviarse como un arreglo',
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

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        $user = auth()->user();

        // Agregar company_id del header o del usuario autenticado
        if (!$this->has('company_id')) {
            $companyId = $this->header('x-company-id');
            if (!$companyId && $user) {
                $userCompany = $user->companies()->first();
                $companyId = $userCompany ? $userCompany->id : null;
            }
            if ($companyId) {
                $this->merge(['company_id' => $companyId]);
            }
        }

        // Defaultear requester_id al usuario autenticado si no se envía
        if (!$this->filled('requester_id') && $user) {
            $this->merge(['requester_id' => $user->id]);
        }
    }
}

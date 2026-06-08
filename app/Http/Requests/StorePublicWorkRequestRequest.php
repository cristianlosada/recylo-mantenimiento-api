<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StorePublicWorkRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Público - no requiere autenticación
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
            // Identificación del activo: por código (QR) o por ID
            'asset_code' => 'nullable|string|exists:assets,code',
            'asset_id'   => 'nullable|integer|exists:assets,id',

            // Empresa (cuando no se tiene asset_code ni asset_id)
            'company_id' => 'nullable|integer|exists:companies,id',

            // Solicitante: usuario registrado o nombre libre
            'requester_id'    => 'nullable|integer|exists:users,id',
            'requester_name'  => 'nullable|string|max:255',
            'requester_email' => 'nullable|email|max:255',
            'requester_phone' => 'nullable|string|max:20',

            // Descripción obligatoria; título se auto-genera
            'description'  => 'required|string',
            'title'        => 'nullable|string|max:255',
            'request_type' => 'sometimes|in:corrective,preventive,improvement,inspection',
            'priority'     => 'sometimes|in:low,medium,high,critical',

            // Estado del equipo (HU-S1)
            'equipment_status' => 'nullable|in:operating_restricted,full_stop',

            // Fotos opcionales
            'attachments'   => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,webp,heic,heif|max:5120',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'asset_code.required' => 'El código del activo es requerido',
            'asset_code.exists' => 'El activo no existe en el sistema',
            
            'requester_name.required' => 'El nombre del solicitante es requerido',
            'requester_name.max' => 'El nombre no puede exceder 255 caracteres',
            
            'requester_email.required' => 'El email del solicitante es requerido',
            'requester_email.email' => 'El email debe ser una dirección válida',
            'requester_email.max' => 'El email no puede exceder 255 caracteres',
            
            'requester_phone.max' => 'El teléfono no puede exceder 20 caracteres',
            
            'title.required' => 'El título de la solicitud es requerido',
            'title.max' => 'El título no puede exceder 255 caracteres',
            
            'description.required' => 'La descripción del problema es requerida',
            
            'request_type.required' => 'El tipo de solicitud es requerido',
            'request_type.in' => 'El tipo de solicitud debe ser: correctivo, preventivo, mejora o inspección',
            
            'priority.in' => 'La prioridad debe ser: baja, media, alta o crítica',
            
            'attachments.array' => 'Los archivos deben enviarse como un array',
            'attachments.max' => 'No se pueden subir más de 5 archivos',
            'attachments.*.file' => 'Cada elemento debe ser un archivo válido',
            'attachments.*.mimes' => 'Solo se permiten archivos: jpg, jpeg, png, pdf',
            'attachments.*.max' => 'Cada archivo no puede superar 5MB',
            
            'location_details.max' => 'Los detalles de ubicación no pueden exceder 500 caracteres',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('priority') || !$this->priority) {
            $this->merge(['priority' => 'medium']);
        }

        if (!$this->has('request_type') || !$this->request_type) {
            $this->merge(['request_type' => 'corrective']);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            // Necesita al menos asset_code, asset_id o company_id
            if (!$this->asset_code && !$this->asset_id && !$this->company_id) {
                $v->errors()->add('asset_code', 'Debe especificar un activo o empresa.');
            }
        });
    }
}

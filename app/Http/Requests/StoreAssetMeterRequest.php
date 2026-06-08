<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;
use App\Models\AssetMeter;

class StoreAssetMeterRequest extends FormRequest
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
     * Reglas de validación para crear medidor de activo
     */
    public function rules(): array
    {
        return [
            // Campos requeridos
            'asset_id' => 'required|integer|exists:assets,id',
            'meter_type' => [
                'required',
                'string',
                'in:' . implode(',', array_keys(AssetMeter::TYPES))
            ],
            'current_reading' => 'required|numeric|min:0|max:999999999.99',
            
            // Campos opcionales
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'alert_threshold' => 'nullable|numeric|min:0|max:999999999.99',
            'max_reading' => 'nullable|numeric|min:0|max:999999999.99|gt:current_reading',
            'is_active' => 'nullable|boolean',
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
            'meter_type.required' => 'El tipo de medidor es requerido',
            'meter_type.in' => 'El tipo de medidor debe ser: hours, kilometers, cycles o units_produced',
            'current_reading.required' => 'La lectura actual es requerida',
            'current_reading.numeric' => 'La lectura actual debe ser numérica',
            'current_reading.min' => 'La lectura actual no puede ser negativa',
            'unit.max' => 'La unidad no puede exceder 50 caracteres',
            'description.max' => 'La descripción no puede exceder 500 caracteres',
            'alert_threshold.numeric' => 'El umbral de alerta debe ser numérico',
            'alert_threshold.min' => 'El umbral de alerta no puede ser negativo',
            'max_reading.numeric' => 'La lectura máxima debe ser numérica',
            'max_reading.min' => 'La lectura máxima no puede ser negativa',
            'max_reading.gt' => 'La lectura máxima debe ser mayor que la lectura actual',
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso',
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
        // Agregar company_id del header o del usuario autenticado
        if (!$this->has('company_id')) {
            // Intentar obtener del header primero
            $companyId = $this->header('x-company-id');
            
            // Si no viene en el header, intentar obtener de la primera empresa del usuario
            if (!$companyId) {
                $user = auth()->user();
                if ($user) {
                    $userCompany = $user->companies()->first();
                    $companyId = $userCompany ? $userCompany->id : null;
                }
            }
            
            if ($companyId) {
                $this->merge(['company_id' => $companyId]);
            }
        }

        // Si no se especifica is_active, usar true por defecto
        if (!$this->has('is_active')) {
            $this->merge([
                'is_active' => true,
            ]);
        }

        // Si no se especifica unit, auto-detectar según meter_type
        if (!$this->has('unit') && $this->has('meter_type')) {
            $meterType = $this->input('meter_type');
            if (isset(AssetMeter::UNITS[$meterType])) {
                $this->merge([
                    'unit' => AssetMeter::UNITS[$meterType],
                ]);
            }
        }
    }
}

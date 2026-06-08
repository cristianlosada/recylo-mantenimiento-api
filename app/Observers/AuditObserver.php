<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditObserver
{
    /**
     * Registrar cuando se crea un modelo
     */
    public function created(Model $model): void
    {
        $this->log($model, 'created', null, $model->getAttributes());
    }

    /**
     * Registrar cuando se actualiza un modelo
     */
    public function updated(Model $model): void
    {
        $original = $model->getOriginal();
        $changes = $model->getChanges();
        
        // Filtrar campos que no queremos auditar
        $sensitiveFields = ['password', 'remember_token', 'mfa_secret'];
        $changes = array_diff_key($changes, array_flip($sensitiveFields));
        $original = array_diff_key($original, array_flip($sensitiveFields));

        if (!empty($changes)) {
            $this->log($model, 'updated', $original, $changes);
        }
    }

    /**
     * Registrar cuando se elimina un modelo
     */
    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted', $model->getAttributes(), null);
    }

    /**
     * Registrar cuando se restaura un modelo (soft delete)
     */
    public function restored(Model $model): void
    {
        $this->log($model, 'restored', null, $model->getAttributes());
    }

    /**
     * Crear el registro de auditoría
     */
    protected function log(Model $model, string $action, ?array $oldValues, ?array $newValues): void
    {
        try {
            $user = Auth::user();
            
            // Obtener el company_id del contexto si está disponible
            $companyId = Request::header('X-Company-ID') ?? Request::input('company_id');

            // Preparar información adicional
            $additionalInfo = [
                'url' => Request::fullUrl(),
                'method' => Request::method(),
            ];

            AuditLog::create([
                'user_id' => $user?->id,
                'company_id' => $companyId,
                'audit_action_id' => $this->getActionId($action),
                'entity_type' => get_class($model),
                'entity_id' => (string) $model->getKey(),
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'additional_info' => json_encode($additionalInfo),
            ]);
        } catch (\Exception $e) {
            // Log error but don't throw exception to avoid breaking the operation
            \Log::error('Error creating audit log: ' . $e->getMessage());
        }
    }

    /**
     * Obtener el ID de la acción desde la tabla audit_actions
     */
    protected function getActionId(string $action): ?int
    {
        static $actions = [];

        if (!isset($actions[$action])) {
            $auditAction = \App\Models\AuditAction::where('name', $action)->first();
            $actions[$action] = $auditAction?->id;
        }

        return $actions[$action];
    }
}

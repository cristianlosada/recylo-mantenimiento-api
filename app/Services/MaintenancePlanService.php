<?php

namespace App\Services;

use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanExecution;
use App\Models\Asset;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class MaintenancePlanService
{
    /**
     * Crear un nuevo plan de mantenimiento
     * 
     * @param array $data
     * @return MaintenancePlan
     * @throws Exception
     */
    public function createPlan(array $data): MaintenancePlan
    {
        try {
            DB::beginTransaction();

            // Validar el activo
            $asset = Asset::findOrFail($data['asset_id']);

            // Validaciones específicas por tipo de plan
            $this->validatePlanData($data, $asset);

            // Generar código único
            $data['code'] = MaintenancePlan::generateCode($data['company_id']);

            // Crear el plan
            $plan = MaintenancePlan::create([
                'company_id' => $data['company_id'],
                'code' => $data['code'],
                'asset_id' => $data['asset_id'],
                'asset_category_id' => $asset->asset_category_id,
                'site_id' => $asset->site_id,
                'name' => $data['plan_name'],
                'description' => $data['description'] ?? null,
                'plan_type' => $data['plan_type'],
                'frequency_type' => $data['frequency_type'] ?? null,
                'frequency_value' => $data['frequency_value'] ?? null,
                'meter_type' => $data['meter_type'] ?? null,
                'meter_threshold' => $data['meter_threshold'] ?? null,
                'trigger_mode' => $data['trigger_mode'] ?? null,
                'priority' => $data['priority'] ?? 'medium',
                'estimated_duration_minutes' => isset($data['estimated_duration_hours']) ? (int)($data['estimated_duration_hours'] * 60) : null,
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'default_assigned_to' => $data['default_assigned_to'] ?? null,
                'is_active' => $data['is_active'] ?? false,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            // Calcular próxima ejecución
            $this->calculateInitialExecution($plan);

            // Agregar checklist templates
            if (!empty($data['checklist_items'])) {
                foreach ($data['checklist_items'] as $item) {
                    $plan->checklistTemplates()->create([
                        'item_order' => $item['item_order'],
                        'item_text' => $item['item_text'],
                        'requires_photo' => $item['requires_photo'] ?? false,
                        'is_mandatory' => $item['is_mandatory'] ?? true,
                    ]);
                }
            }

            // Agregar material templates
            if (!empty($data['estimated_materials'])) {
                foreach ($data['estimated_materials'] as $material) {
                    $plan->materialTemplates()->create([
                        'material_id' => $material['material_id'],
                        'estimated_quantity' => $material['estimated_quantity'],
                        'notes' => $material['notes'] ?? null,
                    ]);
                }
            }

            DB::commit();

            Log::info("Plan de mantenimiento creado: {$plan->id} - {$plan->code}");

            return $plan->fresh(['asset', 'checklistTemplates', 'materialTemplates']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al crear plan de mantenimiento: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validar datos del plan según su tipo
     * 
     * @param array $data
     * @param Asset $asset
     * @throws Exception
     */
    protected function validatePlanData(array $data, Asset $asset): void
    {
        $planType = $data['plan_type'];

        if ($planType === MaintenancePlan::TYPE_TIME_BASED) {
            // Validar campos de tiempo
            if (empty($data['frequency_type']) || empty($data['frequency_value'])) {
                throw new Exception("Para planes basados en tiempo, frequency_type y frequency_value son obligatorios");
            }

            // Verificar que NO tenga campos de medición
            if (!empty($data['meter_type']) || !empty($data['meter_threshold'])) {
                throw new Exception("Planes basados en tiempo no deben tener meter_type ni meter_threshold");
            }
        }

        if ($planType === MaintenancePlan::TYPE_METER_BASED) {
            // Validar campos de medición
            if (empty($data['meter_type']) || empty($data['meter_threshold'])) {
                throw new Exception("Para planes basados en medición, meter_type y meter_threshold son obligatorios");
            }

            // Verificar que NO tenga campos de tiempo
            if (!empty($data['frequency_type']) || !empty($data['frequency_value'])) {
                throw new Exception("Planes basados en medición no deben tener frequency_type ni frequency_value");
            }

            // Verificar que el activo tenga un medidor activo de ese tipo
            $meter = $asset->meters()
                ->where('meter_type', $data['meter_type'])
                ->where('is_active', true)
                ->first();

            if (!$meter) {
                throw new Exception("El activo no tiene un medidor activo de tipo {$data['meter_type']}");
            }

            // Verificar que el medidor tenga al menos una lectura
            if ($meter->readings()->count() === 0) {
                throw new Exception("El medidor debe tener al menos una lectura registrada antes de crear el plan");
            }
        }

        if ($planType === MaintenancePlan::TYPE_HYBRID) {
            // Validar que tenga TODOS los campos
            if (empty($data['frequency_type']) || empty($data['frequency_value']) ||
                empty($data['meter_type']) || empty($data['meter_threshold']) ||
                empty($data['trigger_mode'])) {
                throw new Exception("Para planes híbridos, todos los campos son obligatorios: frequency_type, frequency_value, meter_type, meter_threshold, trigger_mode");
            }

            // Verificar medidor
            $meter = $asset->meters()
                ->where('meter_type', $data['meter_type'])
                ->where('is_active', true)
                ->first();

            if (!$meter) {
                throw new Exception("El activo no tiene un medidor activo de tipo {$data['meter_type']}");
            }
        }
    }

    /**
     * Calcular y establecer la próxima ejecución inicial
     * 
     * @param MaintenancePlan $plan
     */
    protected function calculateInitialExecution(MaintenancePlan $plan): void
    {
        if ($plan->plan_type === MaintenancePlan::TYPE_TIME_BASED || 
            $plan->plan_type === MaintenancePlan::TYPE_HYBRID) {
            // Calcular próxima fecha basada en start_date o ahora
            $base = now();
            $plan->next_execution_date = match ($plan->frequency_type) {
                MaintenancePlan::FREQ_DAILY      => $base->copy()->addDays($plan->frequency_value),
                MaintenancePlan::FREQ_WEEKLY     => $base->copy()->addWeeks($plan->frequency_value),
                MaintenancePlan::FREQ_MONTHLY    => $base->copy()->addMonths($plan->frequency_value),
                MaintenancePlan::FREQ_QUARTERLY  => $base->copy()->addMonths($plan->frequency_value * 3),
                MaintenancePlan::FREQ_SEMIANNUAL => $base->copy()->addMonths($plan->frequency_value * 6),
                MaintenancePlan::FREQ_ANNUAL     => $base->copy()->addYears($plan->frequency_value),
                default                          => $base->copy()->addMonth(),
            };
        }

        if ($plan->plan_type === MaintenancePlan::TYPE_METER_BASED || 
            $plan->plan_type === MaintenancePlan::TYPE_HYBRID) {
            $meter = $plan->asset->meters()->where('meter_type', $plan->meter_type)->first();
            if ($meter) {
                $plan->last_meter_reading = $meter->current_reading;
                $plan->next_meter_threshold = $meter->current_reading + $plan->meter_threshold;
            }
        }

        $plan->save();
    }

    /**
     * Actualizar un plan de mantenimiento
     * 
     * @param int $planId
     * @param array $data
     * @return MaintenancePlan
     * @throws Exception
     */
    public function updatePlan(int $planId, array $data): MaintenancePlan
    {
        try {
            DB::beginTransaction();

            $plan = MaintenancePlan::findOrFail($planId);

            // No permitir cambiar ciertos campos si el plan ya tiene ejecuciones
            if ($plan->executions()->count() > 0) {
                unset($data['plan_type']);
                unset($data['asset_id']);
            }

            // Si se cambian parámetros de frecuencia, recalcular próxima ejecución
            $recalculate = false;
            if (isset($data['frequency_type']) || isset($data['frequency_value']) ||
                isset($data['meter_type']) || isset($data['meter_threshold'])) {
                $recalculate = true;
            }

            // Actualizar campos básicos
            $plan->update(array_filter([
                'name' => $data['name'] ?? $plan->name,
                'description' => $data['description'] ?? $plan->description,
                'frequency_type' => $data['frequency_type'] ?? $plan->frequency_type,
                'frequency_value' => $data['frequency_value'] ?? $plan->frequency_value,
                'meter_type' => $data['meter_type'] ?? $plan->meter_type,
                'meter_threshold' => $data['meter_threshold'] ?? $plan->meter_threshold,
                'trigger_mode' => $data['trigger_mode'] ?? $plan->trigger_mode,
                'priority' => $data['priority'] ?? $plan->priority,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? $plan->estimated_duration_minutes,
                'estimated_cost' => $data['estimated_cost'] ?? $plan->estimated_cost,
                'default_assigned_to' => $data['default_assigned_to'] ?? $plan->default_assigned_to,
                'updated_by' => auth()->id(),
            ]));

            // Actualizar checklist si se proporciona
            if (isset($data['checklist_items'])) {
                $plan->checklistTemplates()->delete();
                foreach ($data['checklist_items'] as $item) {
                    $plan->checklistTemplates()->create($item);
                }
            }

            // Actualizar materiales si se proporciona
            if (isset($data['estimated_materials'])) {
                $plan->materialTemplates()->delete();
                foreach ($data['estimated_materials'] as $material) {
                    $plan->materialTemplates()->create($material);
                }
            }

            // Recalcular próxima ejecución si es necesario
            if ($recalculate) {
                $this->calculateInitialExecution($plan);
            }

            DB::commit();

            Log::info("Plan de mantenimiento actualizado: {$planId}");

            return $plan->fresh(['checklistTemplates', 'materialTemplates']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar plan: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Activar un plan de mantenimiento
     * 
     * @param int $planId
     * @return MaintenancePlan
     * @throws Exception
     */
    public function activatePlan(int $planId): MaintenancePlan
    {
        try {
            $plan = MaintenancePlan::findOrFail($planId);

            // Validar que el activo esté operacional
            if ($plan->asset->status !== 'operational') {
                throw new Exception("El activo debe estar en estado 'operational' para activar el plan");
            }

            // Validar medidor si aplica
            if ($plan->plan_type === MaintenancePlan::TYPE_METER_BASED || 
                $plan->plan_type === MaintenancePlan::TYPE_HYBRID) {
                $meter = $plan->asset->meters()
                    ->where('meter_type', $plan->meter_type)
                    ->where('is_active', true)
                    ->first();

                if (!$meter) {
                    throw new Exception("El activo necesita un medidor activo de tipo {$plan->meter_type}");
                }

                // Verificar lecturas recientes
                if (!$meter->has_recent_readings) {
                    throw new Exception("El medidor debe tener lecturas recientes (últimos 90 días) para activar el plan");
                }
            }

            $plan->update(['is_active' => true]);

            Log::info("Plan de mantenimiento activado: {$planId}");

            return $plan;
        } catch (Exception $e) {
            Log::error("Error al activar plan: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Desactivar un plan de mantenimiento
     * 
     * @param int $planId
     * @return MaintenancePlan
     */
    public function deactivatePlan(int $planId): MaintenancePlan
    {
        $plan = MaintenancePlan::findOrFail($planId);
        $plan->update(['is_active' => false]);

        Log::info("Plan de mantenimiento desactivado: {$planId}");

        return $plan;
    }

    /**
     * Ejecutar manualmente un plan (generar Work Order)
     * 
     * @param int $planId
     * @return WorkOrder
     * @throws Exception
     */
    public function executeManually(int $planId): WorkOrder
    {
        try {
            DB::beginTransaction();

            $plan = MaintenancePlan::findOrFail($planId);

            // Verificar que el plan esté activo
            if (!$plan->is_active) {
                throw new Exception("El plan debe estar activo para ejecutarse");
            }

            // Verificar que no haya una OT pendiente del mismo plan
            $pendingOrder = WorkOrder::where('maintenance_plan_id', $plan->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->first();

            if ($pendingOrder) {
                throw new Exception("Ya existe una Work Order pendiente ({$pendingOrder->code}) para este plan");
            }

            // Generar la Work Order
            $workOrder = $plan->generateWorkOrder();

            // Registrar la ejecución
            MaintenancePlanExecution::create([
                'maintenance_plan_id' => $plan->id,
                'work_order_id' => $workOrder->id,
                'scheduled_date' => now(),
                'status' => MaintenancePlanExecution::STATUS_SCHEDULED,
                'notes' => 'Ejecución manual por usuario: ' . auth()->user()->name,
            ]);

            // Actualizar próxima ejecución
            $plan->updateNextExecution();

            DB::commit();

            Log::info("Plan ejecutado manualmente: {$planId}, Work Order generada: {$workOrder->id}");

            return $workOrder->fresh(['asset', 'checklist', 'materials']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al ejecutar plan manualmente: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar planes que deben ejecutarse (para el scheduler)
     * 
     * @param int|null $companyId Filtrar por empresa
     * @return array ['executed' => count, 'errors' => array]
     */
    public function checkDuePlans(?int $companyId = null): array
    {
        $results = [
            'executed' => 0,
            'errors' => [],
        ];

        $query = MaintenancePlan::active();

        if ($companyId) {
            $query->forCompany($companyId);
        }

        $plans = $query->get();

        foreach ($plans as $plan) {
            try {
                if ($plan->isDue()) {
                    // Verificar que no haya OT pendiente
                    $pendingOrder = WorkOrder::where('maintenance_plan_id', $plan->id)
                        ->whereIn('status', ['scheduled', 'in_progress'])
                        ->first();

                    if ($pendingOrder) {
                        Log::info("Plan {$plan->id} está vencido pero ya tiene OT pendiente: {$pendingOrder->code}");
                        continue;
                    }

                    DB::beginTransaction();

                    // Generar Work Order
                    $workOrder = $plan->generateWorkOrder();

                    // Registrar ejecución
                    MaintenancePlanExecution::create([
                        'maintenance_plan_id' => $plan->id,
                        'work_order_id' => $workOrder->id,
                        'scheduled_date' => now(),
                        'status' => MaintenancePlanExecution::STATUS_SCHEDULED,
                        'notes' => 'Generado automáticamente por el scheduler',
                    ]);

                    // Actualizar próxima ejecución
                    $plan->updateNextExecution();

                    DB::commit();

                    $results['executed']++;
                    
                    Log::info("Plan {$plan->id} ejecutado automáticamente. Work Order: {$workOrder->code}");

                    // Aquí se puede disparar notificación
                    // event(new WorkOrderCreatedFromPlan($workOrder, $plan));
                }
            } catch (Exception $e) {
                DB::rollBack();
                $results['errors'][] = [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'error' => $e->getMessage(),
                ];
                Log::error("Error al ejecutar plan {$plan->id}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Obtener dashboard de planes de mantenimiento
     * 
     * @param int $companyId
     * @return array
     */
    public function getDashboard(int $companyId): array
    {
        return [
            'total_plans' => MaintenancePlan::forCompany($companyId)->count(),
            'active_plans' => MaintenancePlan::forCompany($companyId)->active()->count(),
            'due_today' => MaintenancePlan::forCompany($companyId)->dueToday()->count(),
            'overdue' => MaintenancePlan::forCompany($companyId)->overdue()->count(),
            'upcoming_7_days' => MaintenancePlan::forCompany($companyId)->upcoming(7)->count(),
            'by_type' => [
                'time_based' => MaintenancePlan::forCompany($companyId)->active()->ofType('time_based')->count(),
                'meter_based' => MaintenancePlan::forCompany($companyId)->active()->ofType('meter_based')->count(),
                'hybrid' => MaintenancePlan::forCompany($companyId)->active()->ofType('hybrid')->count(),
            ],
            'executions_this_month' => MaintenancePlanExecution::whereHas('maintenancePlan', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })->whereBetween('scheduled_date', [now()->startOfMonth(), now()->endOfMonth()])->count(),
        ];
    }

    /**
     * Eliminar un plan (soft delete)
     * 
     * @param int $planId
     * @return bool
     * @throws Exception
     */
    public function deletePlan(int $planId): bool
    {
        try {
            $plan = MaintenancePlan::findOrFail($planId);

            // No permitir eliminar si hay Work Orders en progreso
            $activeOrders = WorkOrder::where('maintenance_plan_id', $planId)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->count();

            if ($activeOrders > 0) {
                throw new Exception("No se puede eliminar el plan porque tiene {$activeOrders} Work Order(s) en progreso");
            }

            $plan->delete();

            Log::info("Plan de mantenimiento eliminado: {$planId}");

            return true;
        } catch (Exception $e) {
            Log::error("Error al eliminar plan: " . $e->getMessage());
            throw $e;
        }
    }
}

<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetMeter;
use App\Models\AssetMeterReading;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AssetMeterService
{
    /**
     * Crear un nuevo medidor para un activo
     * 
     * @param int $assetId
     * @param array $data
     * @return AssetMeter
     * @throws Exception
     */
    public function createMeter(int $assetId, array $data): AssetMeter
    {
        try {
            DB::beginTransaction();

            // Validar que el activo existe
            $asset = Asset::findOrFail($assetId);

            // Validar que no exista ya un medidor del mismo tipo
            $existingMeter = AssetMeter::where('asset_id', $assetId)
                ->where('meter_type', $data['meter_type'])
                ->first();

            if ($existingMeter) {
                throw new Exception("El activo ya tiene un medidor de tipo {$data['meter_type']}");
            }

            // Determinar la unidad según el tipo de medidor
            $unit = AssetMeter::UNITS[$data['meter_type']] ?? $data['unit'] ?? '';

            // Crear el medidor
            $meter = AssetMeter::create([
                'asset_id' => $assetId,
                'meter_type' => $data['meter_type'],
                'current_reading' => $data['current_reading'] ?? 0,
                'unit' => $unit,
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);

            // Si se proporciona una lectura inicial > 0, crear el registro
            if (($data['current_reading'] ?? 0) > 0) {
                $meter->recordReading(
                    $data['current_reading'],
                    [
                        'reading_date' => now(),
                        'reading_source' => AssetMeterReading::SOURCE_MANUAL,
                        'recorded_by' => auth()->id(),
                        'notes' => 'Lectura inicial al crear el medidor',
                    ]
                );
            }

            DB::commit();

            Log::info("Medidor creado: {$meter->id} para activo {$assetId}");

            return $meter->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al crear medidor: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar un medidor existente
     * 
     * @param int $meterId
     * @param array $data
     * @return AssetMeter
     * @throws Exception
     */
    public function updateMeter(int $meterId, array $data): AssetMeter
    {
        try {
            $meter = AssetMeter::findOrFail($meterId);

            // No permitir cambiar el tipo de medidor
            unset($data['meter_type']);
            unset($data['asset_id']);

            $meter->update($data);

            Log::info("Medidor actualizado: {$meterId}");

            return $meter->fresh();
        } catch (Exception $e) {
            Log::error("Error al actualizar medidor: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Registrar una nueva lectura en un medidor
     * 
     * @param int $meterId
     * @param array $data
     * @return AssetMeterReading
     * @throws Exception
     */
    public function recordReading(int $meterId, array $data): AssetMeterReading
    {
        try {
            DB::beginTransaction();

            $meter = AssetMeter::findOrFail($meterId);

            // Validar que el medidor esté activo
            if (!$meter->is_active) {
                throw new Exception("El medidor está inactivo y no se pueden registrar lecturas");
            }

            // Validar que la lectura no sea menor que la actual (los medidores no retroceden)
            if ($data['reading_value'] < $meter->current_reading) {
                throw new Exception(
                    "La nueva lectura ({$data['reading_value']}) no puede ser menor que la lectura actual ({$meter->current_reading})"
                );
            }

            // Validar que la fecha no sea futura
            $readingDate = $data['reading_date'] ?? now();
            if ($readingDate > now()) {
                throw new Exception("La fecha de lectura no puede ser futura");
            }

            // Registrar la lectura
            $reading = $meter->recordReading(
                $data['reading_value'],
                [
                    'reading_date' => $readingDate,
                    'reading_source' => $data['reading_source'] ?? AssetMeterReading::SOURCE_MANUAL,
                    'work_order_id' => $data['work_order_id'] ?? null,
                    'maintenance_plan_id' => $data['maintenance_plan_id'] ?? null,
                    'recorded_by' => $data['recorded_by'] ?? auth()->id(),
                    'notes' => $data['notes'] ?? null,
                ]
            );

            // Verificar si hay planes de mantenimiento que ahora están vencidos
            $this->checkMaintenancePlansAfterReading($meter);

            DB::commit();

            Log::info("Lectura registrada: {$reading->id} para medidor {$meterId}");

            return $reading->fresh(['assetMeter', 'recordedBy']);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Error al registrar lectura: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar planes de mantenimiento después de una lectura
     * 
     * @param AssetMeter $meter
     * @return void
     */
    protected function checkMaintenancePlansAfterReading(AssetMeter $meter): void
    {
        try {
            // Buscar planes activos basados en este tipo de medidor
            $plans = $meter->asset->maintenancePlans()
                ->active()
                ->where(function ($query) use ($meter) {
                    $query->where('plan_type', 'meter_based')
                        ->orWhere('plan_type', 'hybrid');
                })
                ->where('meter_type', $meter->meter_type)
                ->get();

            foreach ($plans as $plan) {
                if ($plan->isDue()) {
                    Log::info("Plan de mantenimiento {$plan->id} ({$plan->name}) está vencido después de la lectura");
                    // Aquí podría dispararse una notificación o evento
                    // event(new MaintenancePlanDue($plan));
                }
            }
        } catch (Exception $e) {
            Log::warning("Error al verificar planes de mantenimiento: " . $e->getMessage());
        }
    }

    /**
     * Obtener historial de lecturas de un medidor
     * 
     * @param int $meterId
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getReadings(int $meterId, array $filters = [])
    {
        $query = AssetMeterReading::where('asset_meter_id', $meterId)
            ->with(['recordedBy', 'workOrder']);

        // Filtro por rango de fechas
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->betweenDates($filters['start_date'], $filters['end_date']);
        }

        // Filtro por origen
        if (!empty($filters['reading_source'])) {
            $query->where('reading_source', $filters['reading_source']);
        }

        // Filtro por últimos X días
        if (!empty($filters['last_days'])) {
            $query->lastDays($filters['last_days']);
        }

        return $query->orderBy('reading_date', 'desc')->get();
    }

    /**
     * Obtener estadísticas de un medidor
     * 
     * @param int $meterId
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getStatistics(int $meterId, ?string $startDate = null, ?string $endDate = null): array
    {
        $meter = AssetMeter::findOrFail($meterId);

        $startDate = $startDate ?? now()->subDays(30)->toDateString();
        $endDate = $endDate ?? now()->toDateString();

        $stats = AssetMeterReading::getStatistics($meterId, $startDate, $endDate);

        // Agregar información del medidor
        $stats['meter'] = [
            'id' => $meter->id,
            'type' => $meter->meter_type,
            'type_name' => $meter->type_name,
            'unit' => $meter->unit,
            'current_reading' => $meter->current_reading,
            'last_reading_date' => $meter->last_reading_date,
        ];

        // Agregar próximo mantenimiento
        $stats['next_maintenance'] = $meter->getNextMaintenanceThreshold();

        return $stats;
    }

    /**
     * Desactivar un medidor
     * 
     * @param int $meterId
     * @return AssetMeter
     */
    public function deactivateMeter(int $meterId): AssetMeter
    {
        $meter = AssetMeter::findOrFail($meterId);
        
        $meter->update(['is_active' => false]);

        Log::info("Medidor desactivado: {$meterId}");

        return $meter;
    }

    /**
     * Activar un medidor
     * 
     * @param int $meterId
     * @return AssetMeter
     */
    public function activateMeter(int $meterId): AssetMeter
    {
        $meter = AssetMeter::findOrFail($meterId);
        
        $meter->update(['is_active' => true]);

        Log::info("Medidor activado: {$meterId}");

        return $meter;
    }

    /**
     * Eliminar un medidor (soft delete)
     * 
     * @param int $meterId
     * @return bool
     * @throws Exception
     */
    public function deleteMeter(int $meterId): bool
    {
        try {
            $meter = AssetMeter::findOrFail($meterId);

            // Verificar si hay planes de mantenimiento activos usando este medidor
            $activePlans = $meter->maintenancePlans()->active()->count();
            
            if ($activePlans > 0) {
                throw new Exception(
                    "No se puede eliminar el medidor porque hay {$activePlans} plan(es) de mantenimiento activo(s) que lo utilizan"
                );
            }

            $meter->delete();

            Log::info("Medidor eliminado: {$meterId}");

            return true;
        } catch (Exception $e) {
            Log::error("Error al eliminar medidor: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Importar lecturas masivas desde un array
     * 
     * @param int $meterId
     * @param array $readings
     * @return array ['success' => count, 'errors' => array]
     */
    public function importReadings(int $meterId, array $readings): array
    {
        $meter = AssetMeter::findOrFail($meterId);
        $results = [
            'success' => 0,
            'errors' => [],
        ];

        foreach ($readings as $index => $reading) {
            try {
                $this->recordReading($meterId, [
                    'reading_value' => $reading['value'],
                    'reading_date' => $reading['date'] ?? now(),
                    'reading_source' => AssetMeterReading::SOURCE_IMPORT,
                    'notes' => $reading['notes'] ?? 'Importado',
                ]);
                $results['success']++;
            } catch (Exception $e) {
                $results['errors'][] = [
                    'index' => $index,
                    'data' => $reading,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info("Importación completada para medidor {$meterId}: {$results['success']} éxitos, " . count($results['errors']) . " errores");

        return $results;
    }
}

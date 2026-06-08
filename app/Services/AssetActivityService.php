<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\WorkOrder;
use App\Models\WorkRequest;
use App\Models\AssetActivityLog;
use Illuminate\Support\Facades\Log;

class AssetActivityService
{
    /**
     * Registrar actividad cuando se crea una orden de trabajo
     *
     * @param WorkOrder $workOrder
     * @return void
     */
    public function logWorkOrderCreated(WorkOrder $workOrder): void
    {
        if (!$workOrder->asset_id) {
            return;
        }

        try {
            AssetActivityLog::log(
                $workOrder->asset_id,
                AssetActivityLog::TYPE_WORK_ORDER_CREATED,
                "Nueva Orden de Trabajo: {$workOrder->code}",
                "Se creó la orden de trabajo {$workOrder->code} - {$workOrder->title}",
                [
                    'work_order_id' => $workOrder->id,
                    'performed_by' => $workOrder->created_by,
                    'metadata' => [
                        'priority' => $workOrder->priority,
                        'status' => $workOrder->status,
                        'type' => $workOrder->work_order_type,
                    ]
                ]
            );

            Log::info("Actividad registrada: OT {$workOrder->code} creada para activo {$workOrder->asset_id}");
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de OT creada: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se inicia una orden de trabajo
     *
     * @param WorkOrder $workOrder
     * @return void
     */
    public function logWorkOrderStarted(WorkOrder $workOrder): void
    {
        if (!$workOrder->asset_id) {
            return;
        }

        try {
            AssetActivityLog::log(
                $workOrder->asset_id,
                AssetActivityLog::TYPE_WORK_ORDER_STARTED,
                "Orden de Trabajo Iniciada: {$workOrder->code}",
                "Se inició el trabajo en la orden {$workOrder->code}",
                [
                    'work_order_id' => $workOrder->id,
                    'metadata' => [
                        'assigned_to' => $workOrder->assigned_to,
                        'started_at' => $workOrder->actual_start?->toISOString(),
                    ]
                ]
            );

            Log::info("Actividad registrada: OT {$workOrder->code} iniciada para activo {$workOrder->asset_id}");
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de OT iniciada: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se completa una orden de trabajo
     *
     * @param WorkOrder $workOrder
     * @return void
     */
    public function logWorkOrderCompleted(WorkOrder $workOrder): void
    {
        if (!$workOrder->asset_id) {
            return;
        }

        try {
            AssetActivityLog::log(
                $workOrder->asset_id,
                AssetActivityLog::TYPE_WORK_ORDER_COMPLETED,
                "Orden de Trabajo Completada: {$workOrder->code}",
                "Se completó exitosamente la orden {$workOrder->code}",
                [
                    'work_order_id' => $workOrder->id,
                    'performed_by' => $workOrder->completed_by,
                    'metadata' => [
                        'completed_at' => $workOrder->completed_at?->toISOString(),
                        'duration_hours' => $workOrder->actual_duration_hours,
                        'total_cost' => ($workOrder->actual_labor_cost ?? 0) + 
                                       ($workOrder->actual_material_cost ?? 0) + 
                                       ($workOrder->actual_other_cost ?? 0),
                    ]
                ]
            );

            Log::info("Actividad registrada: OT {$workOrder->code} completada para activo {$workOrder->asset_id}");
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de OT completada: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se cancela una orden de trabajo
     *
     * @param WorkOrder $workOrder
     * @return void
     */
    public function logWorkOrderCancelled(WorkOrder $workOrder): void
    {
        if (!$workOrder->asset_id) {
            return;
        }

        try {
            AssetActivityLog::log(
                $workOrder->asset_id,
                AssetActivityLog::TYPE_WORK_ORDER_CANCELLED,
                "Orden de Trabajo Cancelada: {$workOrder->code}",
                "La orden {$workOrder->code} fue cancelada",
                [
                    'work_order_id' => $workOrder->id,
                    'performed_by' => $workOrder->cancelled_by,
                    'metadata' => [
                        'cancelled_at' => $workOrder->cancelled_at?->toISOString(),
                        'cancellation_reason' => $workOrder->cancellation_reason,
                    ]
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de OT cancelada: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se crea una solicitud de trabajo
     *
     * @param WorkRequest $workRequest
     * @return void
     */
    public function logWorkRequestCreated(WorkRequest $workRequest): void
    {
        if (!$workRequest->asset_id) {
            return;
        }

        try {
            AssetActivityLog::log(
                $workRequest->asset_id,
                AssetActivityLog::TYPE_WORK_REQUEST_CREATED,
                "Nueva Solicitud: {$workRequest->code}",
                "Se reportó la solicitud {$workRequest->code} - {$workRequest->title}",
                [
                    'work_request_id' => $workRequest->id,
                    'performed_by' => $workRequest->requester_id,
                    'metadata' => [
                        'priority' => $workRequest->priority,
                        'status' => $workRequest->status,
                        'source' => $workRequest->requester_id ? 'internal' : 'public',
                    ]
                ]
            );

            Log::info("Actividad registrada: Solicitud {$workRequest->code} creada para activo {$workRequest->asset_id}");
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de solicitud creada: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se aprueba una solicitud de trabajo
     *
     * @param WorkRequest $workRequest
     * @return void
     */
    public function logWorkRequestApproved(WorkRequest $workRequest): void
    {
        if (!$workRequest->asset_id) {
            return;
        }

        try {
            AssetActivityLog::log(
                $workRequest->asset_id,
                AssetActivityLog::TYPE_WORK_REQUEST_APPROVED,
                "Solicitud Aprobada: {$workRequest->code}",
                "La solicitud {$workRequest->code} fue aprobada",
                [
                    'work_request_id' => $workRequest->id,
                    'performed_by' => $workRequest->approved_by,
                    'metadata' => [
                        'approved_at' => $workRequest->approved_at?->toISOString(),
                    ]
                ]
            );

            Log::info("Actividad registrada: Solicitud {$workRequest->code} aprobada para activo {$workRequest->asset_id}");
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de solicitud aprobada: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se rechaza una solicitud de trabajo
     *
     * @param WorkRequest $workRequest
     * @return void
     */
    public function logWorkRequestRejected(WorkRequest $workRequest): void
    {
        if (!$workRequest->asset_id) {
            return;
        }

        try {
            AssetActivityLog::log(
                $workRequest->asset_id,
                AssetActivityLog::TYPE_WORK_REQUEST_REJECTED,
                "Solicitud Rechazada: {$workRequest->code}",
                "La solicitud {$workRequest->code} fue rechazada",
                [
                    'work_request_id' => $workRequest->id,
                    'performed_by' => $workRequest->rejected_by,
                    'metadata' => [
                        'rejected_at' => $workRequest->rejected_at?->toISOString(),
                        'rejection_reason' => $workRequest->rejection_reason,
                    ]
                ]
            );

            Log::info("Actividad registrada: Solicitud {$workRequest->code} rechazada para activo {$workRequest->asset_id}");
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de solicitud rechazada: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se agrega una medición
     *
     * @param int $assetId
     * @param string $measurementType
     * @param float $value
     * @param string $status
     * @return void
     */
    public function logMeasurementAdded(int $assetId, string $measurementType, float $value, string $status): void
    {
        try {
            $title = "Nueva Medición: " . ucfirst($measurementType);
            $description = "Se registró una medición de {$measurementType}: {$value}";

            if ($status !== 'normal') {
                $description .= " - Estado: " . strtoupper($status);
            }

            AssetActivityLog::log(
                $assetId,
                $status === 'normal' ? AssetActivityLog::TYPE_MEASUREMENT_ADDED : AssetActivityLog::TYPE_MEASUREMENT_ALERT,
                $title,
                $description,
                [
                    'metadata' => [
                        'measurement_type' => $measurementType,
                        'value' => $value,
                        'status' => $status,
                    ]
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de medición: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se agrega una nota
     *
     * @param int $assetId
     * @param string $noteContent
     * @return void
     */
    public function logNoteAdded(int $assetId, string $noteContent): void
    {
        try {
            AssetActivityLog::log(
                $assetId,
                AssetActivityLog::TYPE_NOTE_ADDED,
                "Nueva Nota Agregada",
                substr($noteContent, 0, 200) . (strlen($noteContent) > 200 ? '...' : ''),
                []
            );
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de nota: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se agrega un archivo adjunto
     *
     * @param int $assetId
     * @param string $fileName
     * @param string $fileType
     * @return void
     */
    public function logAttachmentAdded(int $assetId, string $fileName, string $fileType): void
    {
        try {
            AssetActivityLog::log(
                $assetId,
                AssetActivityLog::TYPE_ATTACHMENT_ADDED,
                "Nuevo Archivo Adjunto",
                "Se adjuntó el archivo: {$fileName}",
                [
                    'metadata' => [
                        'file_name' => $fileName,
                        'file_type' => $fileType,
                    ]
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de adjunto: " . $e->getMessage());
        }
    }

    /**
     * Registrar actividad cuando se agrega un repuesto
     *
     * @param int $assetId
     * @param string $materialName
     * @return void
     */
    public function logSparePartAdded(int $assetId, string $materialName): void
    {
        try {
            AssetActivityLog::log(
                $assetId,
                AssetActivityLog::TYPE_SPARE_PART_ADDED,
                "Repuesto Asociado",
                "Se asoció el repuesto: {$materialName}",
                [
                    'metadata' => [
                        'material_name' => $materialName,
                    ]
                ]
            );
        } catch (\Exception $e) {
            Log::error("Error al registrar actividad de repuesto: " . $e->getMessage());
        }
    }
}

<?php

namespace App\Exports;

use App\Models\WorkOrder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkOrderExport
{
    private const HEADER_COLOR = 'FF49612E';

    private static array $types = [
        'corrective'  => 'Correctiva',
        'preventive'  => 'Preventiva',
        'predictive'  => 'Predictiva',
        'inspection'  => 'Inspección',
        'emergency'   => 'Emergencia',
        'project'     => 'Proyecto',
    ];

    private static array $priorities = [
        'critical' => 'Crítica',
        'high'     => 'Alta',
        'medium'   => 'Media',
        'low'      => 'Baja',
    ];

    private static array $statuses = [
        'pending'     => 'Pendiente',
        'scheduled'   => 'Programada',
        'in_progress' => 'En Progreso',
        'on_hold'     => 'En Espera',
        'completed'   => 'Completada',
        'validated'   => 'Validada',
        'cancelled'   => 'Cancelada',
    ];

    private static array $headers = [
        'Consecutivo', 'Título', 'Descripción', 'Tipo', 'Prioridad', 'Estado',
        'Activo / Equipo', 'Área / Línea', 'Técnico Asignado',
        'Fecha Creación', 'Inicio Programado', 'Fin Programado', 'Inicio Real', 'Fin Real',
        'Duración Estimada (h)', 'Duración Real (h)', 'Tiempo Inactividad (h)',
        'Costo Labor Real', 'Costo Material Real', 'Costo Otros Real',
        'Tipo Falla', 'Notas Completado',
        'Validado Por', 'Fecha Validación', 'Notas Validación',
        'Cancelado Por', 'Motivo Cancelación',
    ];

    public function __construct(
        private readonly int $companyId,
        private readonly ?string $from   = null,
        private readonly ?string $to     = null,
        private readonly ?string $status = null,
        private readonly ?string $type   = null,
    ) {}

    public function download(string $filename): StreamedResponse
    {
        $rows = WorkOrder::forCompany($this->companyId)
            ->with(['asset.productionLine', 'assignedTo', 'validatedBy', 'cancelledBy'])
            ->when($this->from,   fn($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to,     fn($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->type,   fn($q) => $q->where('work_order_type', $this->type))
            ->orderBy('created_at', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Órdenes de Trabajo');

        ExcelHelper::writeHeaders($sheet, self::$headers, self::HEADER_COLOR);

        if ($rows->isEmpty()) {
            $sheet->setCellValue('A2', 'No hay órdenes de trabajo para los filtros seleccionados.');
        } else {
            foreach ($rows as $i => $wo) {
                ExcelHelper::writeRow($sheet, $i + 2, [
                    $wo->code,
                    $wo->title,
                    $wo->description ?? '',
                    self::$types[$wo->work_order_type]  ?? $wo->work_order_type,
                    self::$priorities[$wo->priority]    ?? $wo->priority,
                    self::$statuses[$wo->status]        ?? $wo->status,
                    $wo->asset ? "{$wo->asset->code} – {$wo->asset->name}" : '',
                    $wo->asset?->productionLine?->name ?? '',
                    $wo->assignedTo?->full_name ?? '',
                    ExcelHelper::formatDate($wo->created_at),
                    $wo->scheduled_start?->format('d/m/Y H:i'),
                    $wo->scheduled_end?->format('d/m/Y H:i'),
                    ExcelHelper::formatDate($wo->actual_start),
                    ExcelHelper::formatDate($wo->actual_end),
                    $wo->estimated_duration_hours,
                    $wo->actual_duration_hours,
                    $wo->downtime_hours,
                    $wo->actual_labor_cost,
                    $wo->actual_material_cost,
                    $wo->actual_other_cost,
                    $wo->failure_type ?? '',
                    $wo->completion_notes ?? '',
                    $wo->validatedBy?->full_name ?? '',
                    ExcelHelper::formatDate($wo->validated_at),
                    $wo->validation_notes ?? '',
                    $wo->cancelledBy?->full_name ?? '',
                    $wo->cancellation_reason ?? '',
                ]);
            }
        }

        ExcelHelper::autoSizeColumns($sheet, count(self::$headers));

        return ExcelHelper::streamResponse($spreadsheet, $filename);
    }
}

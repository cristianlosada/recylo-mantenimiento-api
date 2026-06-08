<?php

namespace App\Exports;

use App\Models\WorkRequest;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkRequestExport
{
    private const HEADER_COLOR = 'FF49612E';

    private static array $requestTypes = [
        'corrective'   => 'Correctiva',
        'preventive'   => 'Preventiva',
        'predictive'   => 'Predictiva',
        'inspection'   => 'Inspección',
        'emergency'    => 'Emergencia',
        'improvement'  => 'Mejora',
        'installation' => 'Instalación',
    ];

    private static array $priorities = [
        'critical' => 'Crítica',
        'high'     => 'Alta',
        'medium'   => 'Media',
        'low'      => 'Baja',
    ];

    private static array $statuses = [
        'pending'      => 'Pendiente',
        'under_review' => 'En Revisión',
        'approved'     => 'Aprobada',
        'rejected'     => 'Rechazada',
        'completed'    => 'Completada',
        'cancelled'    => 'Cancelada',
    ];

    private static array $headers = [
        'Consecutivo', 'Fecha Solicitud', 'Título', 'Descripción',
        'Tipo', 'Prioridad', 'Estado', 'Solicitante', 'Email Solicitante',
        'Activo / Equipo', 'Área / Línea', 'Costo Estimado', 'Costo Real',
        'Horas Estimadas', 'Horas Reales', 'SLA Incumplido',
        'Aprobado / Rechazado Por', 'Fecha Aprobación/Rechazo', 'Motivo Rechazo',
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
        $rows = WorkRequest::forCompany($this->companyId)
            ->with(['asset.productionLine', 'approvedBy', 'rejectedBy'])
            ->when($this->from,   fn($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to,     fn($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->when($this->type,   fn($q) => $q->where('request_type', $this->type))
            ->orderBy('created_at', 'desc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Solicitudes');

        ExcelHelper::writeHeaders($sheet, self::$headers, self::HEADER_COLOR);

        if ($rows->isEmpty()) {
            $sheet->setCellValue('A2', 'No hay solicitudes de trabajo para los filtros seleccionados.');
        } else {
            foreach ($rows as $i => $wr) {
                $resolvedBy = $wr->approvedBy?->full_name ?? $wr->rejectedBy?->full_name ?? '';
                $resolvedAt = $wr->approved_at ?? $wr->rejected_at;
                ExcelHelper::writeRow($sheet, $i + 2, [
                    $wr->code,
                    ExcelHelper::formatDate($wr->created_at),
                    $wr->title,
                    $wr->description ?? '',
                    self::$requestTypes[$wr->request_type] ?? $wr->request_type,
                    self::$priorities[$wr->priority]       ?? $wr->priority,
                    self::$statuses[$wr->status]           ?? $wr->status,
                    $wr->requester_name,
                    $wr->requester_email,
                    $wr->asset ? "{$wr->asset->code} – {$wr->asset->name}" : '',
                    $wr->asset?->productionLine?->name ?? '',
                    $wr->estimated_cost,
                    $wr->actual_cost,
                    $wr->estimated_hours,
                    $wr->actual_hours,
                    $wr->sla_breached ? 'Sí' : 'No',
                    $resolvedBy,
                    ExcelHelper::formatDate($resolvedAt, 'd/m/Y'),
                    $wr->rejection_reason ?? '',
                ]);
            }
        }

        ExcelHelper::autoSizeColumns($sheet, count(self::$headers));

        return ExcelHelper::streamResponse($spreadsheet, $filename);
    }
}

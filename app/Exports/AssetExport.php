<?php

namespace App\Exports;

use App\Models\Asset;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetExport
{
    private const HEADER_COLOR = 'FF49612E';

    private static array $headers = [
        'Código', 'Nombre', 'Descripción', 'Categoría', 'Sistema',
        'Área / Línea', 'Sitio', 'Estado', 'Prioridad',
        'Marca', 'Modelo', 'N° Serie', 'Año Fabricación', 'Activo',
        'Fecha Instalación', 'Fecha Compra', 'Costo Compra', 'Centro Costo',
    ];

    public function __construct(
        private readonly int $companyId,
        private readonly ?string $status   = null,
        private readonly ?bool   $isActive = null,
    ) {}

    public function download(string $filename): StreamedResponse
    {
        $rows = Asset::where('company_id', $this->companyId)
            ->with(['companySite', 'productionLine', 'system', 'category', 'status', 'priority'])
            ->when($this->status, fn($q) => $q->whereHas('status', fn($s) => $s->where('slug', $this->status)))
            ->when(!is_null($this->isActive), fn($q) => $q->where('is_active', $this->isActive))
            ->orderBy('code')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Activos');

        ExcelHelper::writeHeaders($sheet, self::$headers, self::HEADER_COLOR);

        if ($rows->isEmpty()) {
            $sheet->setCellValue('A2', 'No hay activos para los filtros seleccionados.');
        } else {
            foreach ($rows as $i => $asset) {
                ExcelHelper::writeRow($sheet, $i + 2, [
                    $asset->code,
                    $asset->name,
                    $asset->description ?? '',
                    $asset->category?->name      ?? '',
                    $asset->system?->name        ?? '',
                    $asset->productionLine?->name ?? '',
                    $asset->companySite?->name   ?? '',
                    $asset->status?->name        ?? '',
                    $asset->priority?->name      ?? '',
                    $asset->brand                ?? '',
                    $asset->model                ?? '',
                    $asset->serial_number        ?? '',
                    $asset->manufacturing_year,
                    $asset->is_active ? 'Sí' : 'No',
                    ExcelHelper::formatDate($asset->installation_date, 'd/m/Y'),
                    ExcelHelper::formatDate($asset->purchase_date, 'd/m/Y'),
                    $asset->purchase_cost,
                    $asset->cost_center ?? '',
                ]);
            }
        }

        ExcelHelper::autoSizeColumns($sheet, count(self::$headers));

        return ExcelHelper::streamResponse($spreadsheet, $filename);
    }
}

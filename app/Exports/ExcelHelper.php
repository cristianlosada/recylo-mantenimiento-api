<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelHelper
{
    public static function writeHeaders(Worksheet $sheet, array $headers, string $argbColor): void
    {
        foreach ($headers as $col => $label) {
            $sheet->setCellValue([$col + 1, 1], $label);
        }

        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));

        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size'  => 11,
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => $argbColor],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(20);
    }

    public static function writeRow(Worksheet $sheet, int $rowIndex, array $values): void
    {
        foreach (array_values($values) as $col => $value) {
            $sheet->setCellValue([$col + 1, $rowIndex], $value ?? '');
        }
    }

    public static function autoSizeColumns(Worksheet $sheet, int $count): void
    {
        for ($col = 1; $col <= $count; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }
    }

    public static function formatDate(?\DateTimeInterface $date, string $format = 'd/m/Y H:i'): string
    {
        if (!$date) return '';
        return \Carbon\Carbon::instance($date)->setTimezone(config('app.timezone'))->format($format);
    }

    public static function streamResponse(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(
            function () use ($writer) { $writer->save('php://output'); },
            200,
            [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Cache-Control'       => 'max-age=0',
            ]
        );
    }
}

<?php

namespace App\Services\Fmy;

/**
 * 导出工具服务 —— 纯格式转换，不碰业务
 */
class ExportService
{
    /**
     * 生成 CSV 内容（不含 HTTP 头）
     */
    public function csvContent(array $headers, array $rows): string
    {
        $escapedHeaders = array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $headers);
        $csv = implode(',', $escapedHeaders) . "\n";
        foreach ($rows as $row) {
            $escaped = array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $row);
            $csv .= implode(',', $escaped) . "\n";
        }
        // UTF-8 BOM，确保 Excel 正确识别编码
        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * 生成 Excel (.xlsx) 内容（不含 HTTP 头）
     */
    public function xlsxContent(array $headers, array $rows): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Sheet1');

        $colLetter = fn ($index) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);

        $headerStyle = [
            'font' => ['bold' => true, 'size' => 11],
            'fill' => [
                'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8EAF6'],
            ],
        ];
        foreach (array_values($headers) as $col => $title) {
            $sheet->setCellValue($colLetter($col + 1) . '1', $title);
        }
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);

        foreach ($rows as $rowIdx => $row) {
            foreach (array_values($row) as $colIdx => $value) {
                $sheet->setCellValue($colLetter($colIdx + 1) . ($rowIdx + 2), $value);
            }
        }

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return ob_get_clean();
    }
}

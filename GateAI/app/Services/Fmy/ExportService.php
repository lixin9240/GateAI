<?php

namespace App\Services\Fmy;

/**
 * 导出工具服务 —— 纯格式转换，不碰业务
 */
class ExportService
{
    /**
     * 生成 CSV 响应
     */
    public function csv(array $headers, array $rows, string $filename): \Illuminate\Http\Response
    {
        $escapedHeaders = array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $headers);
        $csv = implode(',', $escapedHeaders) . "\n";
        foreach ($rows as $row) {
            $escaped = array_map(fn ($v) => '"' . str_replace('"', '""', $v) . '"', $row);
            $csv .= implode(',', $escaped) . "\n";
        }

        return response("\xEF\xBB\xBF" . $csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    /**
     * 生成 Excel (.xlsx) 响应
     */
    public function xlsx(array $headers, array $rows, string $filename): \Illuminate\Http\Response
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Sheet1');

        $colLetter = fn ($index) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);

        // 表头
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

        // 数据行
        foreach ($rows as $rowIdx => $row) {
            foreach (array_values($row) as $colIdx => $value) {
                $sheet->setCellValue($colLetter($colIdx + 1) . ($rowIdx + 2), $value);
            }
        }

        // 自动列宽
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200, [
            'Content-Type'              => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition'       => "attachment; filename=\"{$filename}.xlsx\"",
            'Cache-Control'             => 'max-age=0',
        ]);
    }
}

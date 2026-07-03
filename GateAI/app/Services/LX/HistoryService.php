<?php
// 历史查询服务
namespace App\Services\LX;

use App\Models\HistoryExportTask;
use App\Models\MonitoringData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HistoryService
{
    public function queryData(array $filters): array
    {
        $query = MonitoringData::where('reservoir_id', $filters['reservoir_id']);

        if (! empty($filters['equipment_id'])) {
            // equipment_id 不直接在 monitoring_data 中，通过 edge_node 间接关联
        }

        $query->whereBetween('timestamp', [$filters['start_time'], $filters['end_time']]);

        $perPage = min((int) ($filters['page_size'] ?? 1000), 10000);
        $data    = $query->orderBy('timestamp')->paginate(
            perPage: $perPage,
            page: (int) ($filters['page'] ?? 1)
        );

        $points = $data->map(fn ($row) => [
            'timestamp' => $row->timestamp,
            'values'    => [
                'upstream_level'   => $row->upstream_level,
                'downstream_level' => $row->downstream_level,
                'inflow_rate'      => $row->inflow_rate,
                'outflow_rate'     => $row->outflow_rate,
                'gate_opening'     => $row->gate_opening,
                'power_output'     => $row->power_output,
            ],
        ]);

        return [
            'start_time' => $filters['start_time'],
            'end_time'   => $filters['end_time'],
            'interval'   => $filters['interval'] ?? '1m',
            'total'      => $data->total(),
            'points'     => $points,
        ];
    }

    public function export(array $data): array
    {
        $taskNo = 'EXP-' . date('YmdHis') . '-' . strtoupper(Str::random(4));
        $format = $data['format'] ?? 'csv';

        $task = HistoryExportTask::create([
            'task_no'        => $taskNo,
            'equipment_ids'  => $data['equipment_ids'],
            'start_time'     => $data['start_time'],
            'end_time'       => $data['end_time'],
            'metrics'        => $data['metrics'],
            'format'         => $format,
            'interval'       => $data['interval'] ?? '1m',
            'file_name'      => $data['file_name'] ?? null,
            'email'          => $data['email'] ?? null,
            'status'         => 'processing',
            'estimated_time' => 60,
            'created_by'     => auth()->id(),
        ]);

        try {
            // 查询数据
            $rows = MonitoringData::whereIn('reservoir_id', $data['equipment_ids'] ?? [])
                ->orWhereIn('edge_node_id', $data['equipment_ids'] ?? [])
                ->whereBetween('timestamp', [$data['start_time'], $data['end_time']])
                ->orderBy('timestamp')
                ->limit(100000)
                ->get();

            // 生成 CSV
            $csv = $this->generateCsv($rows, $data['metrics']);
            $size = strlen($csv);

            // 上传 OSS
            $fileName = $data['file_name'] ?? $taskNo;
            $ossPath  = 'exports/' . date('Ym') . '/' . $fileName . '.' . $format;
            Storage::disk('oss')->put($ossPath, $csv);
            $downloadUrl = Storage::disk('oss')->url($ossPath);

            $task->update([
                'status'      => 'completed',
                'progress'    => 100,
                'file_size'   => $size,
                'download_url' => $downloadUrl,
                'completed_at' => now(),
                'expire_at'    => now()->addDay(),
            ]);
        } catch (\Throwable $e) {
            $task->update([
                'status'    => 'failed',
                'error_msg' => $e->getMessage(),
            ]);
        }

        return [
            'task_id'        => $task->task_no,
            'status'         => $task->status,
            'estimated_size' => $task->estimated_size ?? '—',
            'estimated_time' => $task->estimated_time,
        ];
    }

    private function generateCsv($rows, array $metrics): string
    {
        $headers = array_merge(['timestamp'], $metrics);
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, $headers);

        foreach ($rows as $row) {
            $line = [$row->timestamp];
            foreach ($metrics as $metric) {
                $line[] = $row->{$metric} ?? '';
            }
            fputcsv($csv, $line);
        }

        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return $content;
    }

    public function exportStatus(string $taskId): array
    {
        $task = HistoryExportTask::where('task_no', $taskId)->firstOrFail();

        return [
            'task_id'      => $task->task_no,
            'status'       => $task->status,
            'progress'     => $task->progress,
            'file_size'    => $task->file_size,
            'download_url' => $task->download_url,
            'error_msg'    => $task->error_msg,
        ];
    }
}

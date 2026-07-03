<?php
// 历史查询服务
namespace App\Services\LX;

use App\Models\HistoryExportTask;
use App\Models\MonitoringData;
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
        $task = HistoryExportTask::create([
            'task_no'        => 'EXP-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
            'equipment_ids'  => $data['equipment_ids'],
            'start_time'     => $data['start_time'],
            'end_time'       => $data['end_time'],
            'metrics'        => $data['metrics'],
            'format'         => $data['format'] ?? 'csv',
            'interval'       => $data['interval'] ?? '1m',
            'file_name'      => $data['file_name'] ?? null,
            'email'          => $data['email'] ?? null,
            'status'         => 'queued',
            'estimated_size' => '—',
            'estimated_time' => 60,
            'created_by'     => auth()->id(),
        ]);

        return [
            'task_id'        => $task->task_no,
            'status'         => $task->status,
            'estimated_size' => $task->estimated_size,
            'estimated_time' => $task->estimated_time,
        ];
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

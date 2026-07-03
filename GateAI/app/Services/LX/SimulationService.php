<?php

namespace App\Services\LX;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Models\SimulationResult;
use App\Models\SimulationResultTimeSeries;
use App\Models\SimulationScenario;
use App\Models\SimulationTask;
use Illuminate\Support\Str;

class SimulationService
{
    public function start(array $data): array
    {
        $scenario = SimulationScenario::findOrFail($data['scenario_id']);

        if ($scenario->status === 'draft') {
            throw new BusinessException('草稿场景不允许启动仿真', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        $running = SimulationTask::where('scenario_id', $data['scenario_id'])
            ->whereIn('status', ['pending', 'running'])
            ->exists();

        if ($running) {
            throw new BusinessException('该场景已有运行中的仿真任务', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        $duration = $data['duration'] ?? $scenario->duration;
        $speed    = $data['speed'] ?? $scenario->speed;

        $task = SimulationTask::create([
            'task_no'            => 'SIM-' . date('YmdHis') . '-' . strtoupper(Str::random(4)),
            'scenario_id'        => $data['scenario_id'],
            'model_id'           => $data['model_id'],
            'duration'           => $duration,
            'speed'              => $speed,
            'params'             => $data['params'] ?? null,
            'status'             => 'running',
            'start_time'         => now(),
            'estimated_end_time' => now()->addSeconds((int) ($duration / $speed)),
            'ws_endpoint'        => 'wss://' . request()->getHost() . '/api/simulation/stream-data?simulationId=' . 'SIM-' . date('YmdHis'),
            'created_by'         => auth()->id(),
        ]);

        $scenario->increment('usage_count');

        return [
            'simulation_id'      => $task->task_no,
            'status'             => $task->status,
            'start_time'         => $task->start_time,
            'estimated_end_time' => $task->estimated_end_time,
            'ws_endpoint'        => $task->ws_endpoint,
        ];
    }

    public function result(string $taskId, array $filters): array
    {
        $task = SimulationTask::where('task_no', $taskId)->firstOrFail();

        $result = SimulationResult::where('simulation_id', $task->id)->first();

        $summary = $result?->summary ?? $task->result_summary;

        $tsQuery = SimulationResultTimeSeries::where('result_id', $result?->id);

        if (! empty($filters['metric_type'])) {
            // metric_type 过滤在 values JSON 中，此处仅做参数接收
        }

        $aggregation = $filters['aggregation'] ?? 'raw';
        $points = $tsQuery->orderBy('timestamp')->get();

        if ($aggregation !== 'raw' && $points->isNotEmpty()) {
            $points = $this->aggregate($points, $aggregation);
        }

        return [
            'summary' => $summary,
            'total'   => $points->count(),
            'points'  => $points,
        ];
    }

    public function report(string $taskId, array $data): array
    {
        $task = SimulationTask::where('task_no', $taskId)->firstOrFail();

        $reportId = 'RPT-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

        SimulationResult::updateOrCreate(
            ['simulation_id' => $task->id],
            [
                'scenario_id'   => $task->scenario_id,
                'status'        => $task->status,
                'report_id'     => $reportId,
                'report_status' => 'pending',
                'start_time'    => $task->start_time,
                'end_time'      => $task->end_time,
            ]
        );

        return [
            'report_id' => $reportId,
            'status'    => 'queued',
        ];
    }

    private function aggregate($points, string $type)
    {
        // 聚合逻辑占位，实际实现需按时间窗口分组
        return $points;
    }
}

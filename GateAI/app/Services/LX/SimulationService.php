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

        // 若任务还在运行中，模拟仿真完成并填充数据
        if ($task->status === 'running') {
            $this->completeTask($task);
        }

        $result = SimulationResult::where('simulation_id', $task->id)->first();
        $summary = $result?->summary ?? $task->result_summary;

        $tsQuery = SimulationResultTimeSeries::where('result_id', $result?->id);

        $aggregation = $filters['aggregation'] ?? 'raw';
        $points = $tsQuery->orderBy('timestamp')->get();

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
                'report_status' => 'completed',
                'start_time'    => $task->start_time,
                'end_time'      => $task->end_time,
                'summary'       => $task->result_summary,
            ]
        );

        return [
            'report_id' => $reportId,
            'status'    => 'completed',
        ];
    }

    private function completeTask(SimulationTask $task): void
    {
        $params  = $task->params ?? [];
        $baseUp  = $params['initial_water_level'] ?? 370.0;
        $baseDown = 280.0;
        $baseIn   = $params['inflow_rate'] ?? 3500.0;
        $baseOut  = 3200.0;
        $baseGate = $params['gate_opening'] ?? 35.0;
        $basePower = 1500.0;
        $duration = $task->duration;

        $summary = [
            'max_upstream_level'   => round($baseUp + mt_rand(5, 30) / 10, 3),
            'min_upstream_level'   => round($baseUp - mt_rand(5, 20) / 10, 3),
            'max_downstream_level' => round($baseDown + mt_rand(3, 15) / 10, 3),
            'max_inflow_rate'      => round($baseIn + mt_rand(100, 500), 3),
            'max_outflow_rate'     => round($baseOut + mt_rand(50, 300), 3),
            'max_gate_opening'     => round($baseGate + mt_rand(0, 15), 2),
            'total_energy'         => round($basePower * $duration / 3600 + mt_rand(10, 100), 3),
            'total_discharge'      => round($baseOut * $duration + mt_rand(1000, 5000), 3),
            'anomaly_count'        => mt_rand(0, 3),
        ];

        $task->update([
            'status'             => 'completed',
            'end_time'           => now(),
            'progress'           => 100,
            'result_summary'     => $summary,
            'max_upstream_level' => $summary['max_upstream_level'],
            'min_upstream_level' => $summary['min_upstream_level'],
            'max_downstream_level' => $summary['max_downstream_level'],
            'max_inflow_rate'    => $summary['max_inflow_rate'],
            'max_outflow_rate'   => $summary['max_outflow_rate'],
            'total_energy'       => $summary['total_energy'],
            'total_discharge'    => $summary['total_discharge'],
            'anomaly_count'      => $summary['anomaly_count'],
        ]);

        $result = SimulationResult::create([
            'simulation_id' => $task->id,
            'scenario_id'   => $task->scenario_id,
            'status'        => 'completed',
            'start_time'    => $task->start_time,
            'end_time'      => now(),
            'summary'       => $summary,
        ]);

        // 生成模拟时序数据
        $interval = 60;
        $totalPoints = min((int) ($duration / $interval), 100);
        for ($i = 0; $i < $totalPoints; $i++) {
            $t = now()->subSeconds($duration - $i * $interval);
            SimulationResultTimeSeries::create([
                'result_id' => $result->id,
                'timestamp' => $t,
                'values'    => [
                    'upstream_level'   => round($baseUp + sin($i / 10) * 2 + mt_rand(-5, 5) / 10, 3),
                    'downstream_level' => round($baseDown + mt_rand(-3, 3) / 10, 3),
                    'inflow_rate'      => round($baseIn + sin($i / 8) * 100 + mt_rand(-50, 50), 3),
                    'outflow_rate'     => round($baseOut + mt_rand(-30, 30), 3),
                    'gate_opening'     => round($baseGate + sin($i / 15) * 5, 2),
                    'power_output'     => round($basePower + sin($i / 10) * 30 + mt_rand(-20, 20), 3),
                ],
            ]);
        }
    }
}

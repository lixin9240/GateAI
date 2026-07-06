<?php

namespace App\Services\LX;

use App\Enums\ResponseCode;
use App\Exceptions\BusinessException;
use App\Jobs\SimulateTask as SimulateJob;
use App\Models\SimulationResult;
use App\Models\SimulationResultTimeSeries;
use App\Models\SimulationScenario;
use App\Models\SimulationTask;
use App\Support\LogHelper;
use Illuminate\Support\Str;

class SimulationService
{
    // 启动仿真任务
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

        $taskNo = 'SIM-' . date('YmdHis') . '-' . strtoupper(Str::random(4));

        $task = SimulationTask::create([
            'task_no'            => $taskNo,
            'scenario_id'        => $data['scenario_id'],
            'model_id'           => $data['model_id'],
            'duration'           => $duration,
            'speed'              => $speed,
            'params'             => $data['params'] ?? null,
            'status'             => 'running',
            'start_time'         => now(),
            'estimated_end_time' => now()->addSeconds((int) ($duration / $speed)),
            'ws_endpoint'        => sprintf('%s://%s:%s/app',
                    env('REVERB_SCHEME', 'http'),
                    env('REVERB_HOST', '47.108.169.152'),
                    env('REVERB_PORT', 8089)
                ),
            'created_by'         => auth()->id(),
        ]);

        $scenario->increment('usage_count');

        SimulateJob::dispatch($task->id, $taskNo, $duration, $speed, $data['params'] ?? []);

        LogHelper::business('[仿真] 启动仿真任务', [
            'task_no'     => $taskNo,
            'scenario_id' => $data['scenario_id'],
            'user_id'     => auth()->id(),
        ], 'info', 'SIMULATION_START');

        return [
            'simulation_id'      => $taskNo,
            'status'             => $task->status,
            'start_time'         => $task->start_time->toDateTimeString(),
            'estimated_end_time' => $task->estimated_end_time->toDateTimeString(),
            'ws_endpoint'        => $task->ws_endpoint,
        ];
    }

    public function result(string $taskId, array $filters): array
    {
        $task = SimulationTask::where('task_no', $taskId)->firstOrFail();

        // 任务还在运行中：返回已有数据 + 当前进度
        if ($task->status === 'running') {
            return [
                'summary'   => null,
                'total'     => 0,
                'points'    => [],
                'progress'  => $task->progress ?? 0,
                'status'    => 'running',
            ];
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

    // 暂停仿真
    public function pause(string $taskId): array
    {
        $task = SimulationTask::where('task_no', $taskId)->firstOrFail();

        if ($task->status !== 'running') {
            throw new BusinessException('仅运行中的仿真任务可暂停', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        $task->update(['status' => 'paused']);

        LogHelper::business('[仿真] 暂停仿真任务', [
            'task_no' => $taskId,
            'user_id' => auth()->id(),
        ], 'info', 'SIMULATION_PAUSE');

        return [
            'simulation_id' => $taskId,
            'status'        => 'paused',
            'progress'      => $task->progress,
        ];
    }

    // 恢复仿真
    public function resume(string $taskId): array
    {
        $task = SimulationTask::where('task_no', $taskId)->firstOrFail();

        if ($task->status !== 'paused') {
            throw new BusinessException('仅已暂停的仿真任务可恢复', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        $remaining = (int) ($task->duration * (1 - ($task->progress ?? 0) / 100) / $task->speed);
        $task->update([
            'status'             => 'running',
            'estimated_end_time' => now()->addSeconds($remaining),
        ]);

        LogHelper::business('[仿真] 恢复仿真任务', [
            'task_no' => $taskId,
            'user_id' => auth()->id(),
        ], 'info', 'SIMULATION_RESUME');

        return [
            'simulation_id'      => $taskId,
            'status'             => 'running',
            'progress'           => $task->progress,
            'estimated_end_time' => $task->estimated_end_time->toDateTimeString(),
        ];
    }

    // 重置仿真
    public function reset(string $taskId): array
    {
        $task = SimulationTask::where('task_no', $taskId)->firstOrFail();

        if (!in_array($task->status, ['running', 'paused'])) {
            throw new BusinessException('仅运行中或已暂停的仿真任务可重置', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        // 删除已有结果数据
        $result = SimulationResult::where('simulation_id', $task->id)->first();
        if ($result) {
            SimulationResultTimeSeries::where('result_id', $result->id)->delete();
            $result->delete();
        }

        $task->update([
            'status'             => 'terminated',
            'end_time'           => now(),
            'progress'           => 0,
            'result_summary'     => null,
            'anomaly_count'      => null,
            'max_upstream_level' => null,
            'min_upstream_level' => null,
            'max_downstream_level' => null,
            'max_inflow_rate'    => null,
            'max_outflow_rate'   => null,
            'total_energy'       => null,
            'total_discharge'    => null,
            'error_msg'          => null,
        ]);

        LogHelper::business('[仿真] 重置仿真任务', [
            'task_no' => $taskId,
            'user_id' => auth()->id(),
        ], 'info', 'SIMULATION_RESET');

        return [
            'simulation_id' => $taskId,
            'status'        => 'terminated',
        ];
    }

    // 仿真中调节闸门开度
    public function adjustGate(string $taskId, array $data): array
    {
        $task = SimulationTask::where('task_no', $taskId)->firstOrFail();

        if ($task->status !== 'running') {
            throw new BusinessException('仅运行中的仿真任务可调节闸门', ResponseCode::STATUS_CANNOT_OPERATE);
        }

        $gateOpening = $data['gate_opening'];

        $params = $task->params ?? [];
        $params['gate_opening'] = $gateOpening;

        $task->update(['params' => $params]);

        LogHelper::business('[仿真] 仿真中调节闸门开度', [
            'task_no'      => $taskId,
            'gate_opening' => $gateOpening,
            'user_id'      => auth()->id(),
        ], 'info', 'SIMULATION_GATE_ADJUST');

        return [
            'simulation_id' => $taskId,
            'gate_opening'  => $gateOpening,
            'status'        => $task->status,
            'progress'      => $task->progress,
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

        LogHelper::business('[仿真] 提交仿真报告', [
            'task_no'  => $taskId,
            'report_id' => $reportId,
            'user_id'  => auth()->id(),
        ], 'info', 'SIMULATION_REPORT');

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

        LogHelper::business('[仿真] 仿真任务完成', [
            'task_no'     => $task->task_no,
            'scenario_id' => $task->scenario_id,
        ], 'info', 'SIMULATION_COMPLETE');

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

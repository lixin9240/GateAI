<?php

namespace App\Jobs;

use App\Events\SimulationProgress;
use App\Models\SimulationResult;
use App\Models\SimulationResultTimeSeries;
use App\Models\SimulationTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SimulateTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $taskId;
    public string $taskNo;
    public int $duration;
    public float $speed;
    public array $params;

    public function __construct(int $taskId, string $taskNo, int $duration, float $speed, array $params)
    {
        $this->taskId   = $taskId;
        $this->taskNo   = $taskNo;
        $this->duration = $duration;
        $this->speed    = $speed;
        $this->params   = $params;
    }

    public function handle(): void
    {
        $task = SimulationTask::find($this->taskId);
        if (! $task || $task->status !== 'running') {
            return;
        }

        $baseUp    = $this->params['initial_water_level'] ?? 370.0;
        $baseDown  = 280.0;
        $baseIn    = $this->params['inflow_rate'] ?? 3500.0;
        $baseOut   = 3200.0;
        $baseGate  = $this->params['gate_opening'] ?? 35.0;
        $basePower = 1500.0;

        $totalSteps  = max(10, (int) ($this->duration / 60));
        $stepSeconds = $this->duration / $totalSteps;
        $realSeconds = $stepSeconds / $this->speed;
        $result      = SimulationResult::create([
            'simulation_id' => $task->id,
            'scenario_id'   => $task->scenario_id,
            'status'        => 'running',
            'start_time'    => $task->start_time,
        ]);

        $anomalies    = [];
        $maxUp = $baseUp; $minUp = $baseUp; $maxDown = $baseDown;
        $maxIn = $baseIn; $maxOut = $baseOut; $maxGate = $baseGate;
        $totalEnergy = 0;

        for ($step = 1; $step <= $totalSteps; $step++) {
            $progress = round(($step / $totalSteps) * 100, 1);

            $up    = round($baseUp + sin($step * 0.3) * 3 + (mt_rand(-5, 5) / 10), 2);
            $down  = round($baseDown + sin($step * 0.2) * 1 + (mt_rand(-3, 3) / 10), 2);
            $in    = round($baseIn + sin($step * 0.25) * 100 + mt_rand(-50, 50), 1);
            $out   = round($baseOut + mt_rand(-30, 30), 1);
            $gate  = round(max(0, min(100, $baseGate + sin($step * 0.4) * 10)), 1);
            $power = round($basePower + sin($step * 0.35) * 30 + mt_rand(-20, 20), 1);

            $maxUp   = max($maxUp, $up);
            $minUp   = min($minUp, $up);
            $maxDown = max($maxDown, $down);
            $maxIn   = max($maxIn, $in);
            $maxOut  = max($maxOut, $out);
            $maxGate = max($maxGate, $gate);
            $totalEnergy += $power * $stepSeconds / 3600;

            // 随机异常 (5% 概率)
            $anomalyEvents = [];
            if (mt_rand(1, 100) <= 5) {
                $anomalyEvents[] = [
                    'type'      => ['water_level_spike', 'flow_surge', 'gate_stuck'][mt_rand(0, 2)],
                    'level'     => ['warning', 'danger'][mt_rand(0, 1)],
                    'message'   => '仿真异常事件 #' . count($anomalies) + 1,
                    'timestamp' => now()->toDateTimeString(),
                ];
                $anomalies = array_merge($anomalies, $anomalyEvents);
            }

            $ts = SimulationResultTimeSeries::create([
                'result_id' => $result->id,
                'timestamp' => now(),
                'values'    => compact('up', 'down', 'in', 'out', 'gate', 'power'),
            ]);

            broadcast(new SimulationProgress(
                $this->taskNo,
                $progress,
                [
                    'upstream_level'   => $up,
                    'downstream_level' => $down,
                    'inflow_rate'      => $in,
                    'outflow_rate'     => $out,
                    'gate_opening'     => $gate,
                    'power_output'     => $power,
                ],
                $anomalyEvents,
                'running',
            ));

            // 模拟真实耗时
            if ($realSeconds > 0.1) {
                usleep((int) ($realSeconds * 1_000_000));
            }
        }

        // 完成
        $summary = [
            'max_upstream_level'   => round($maxUp, 2),
            'min_upstream_level'   => round($minUp, 2),
            'max_downstream_level' => round($maxDown, 2),
            'max_inflow_rate'      => round($maxIn, 1),
            'max_outflow_rate'     => round($maxOut, 1),
            'max_gate_opening'     => round($maxGate, 1),
            'total_energy'         => round($totalEnergy, 2),
            'total_discharge'      => round($baseOut * $this->duration + mt_rand(1000, 5000), 2),
            'anomaly_count'        => count($anomalies),
        ];

        $task->update([
            'status'              => 'completed',
            'end_time'            => now(),
            'progress'            => 100,
            'result_summary'      => $summary,
            'max_upstream_level'  => $summary['max_upstream_level'],
            'min_upstream_level'  => $summary['min_upstream_level'],
            'max_downstream_level'=> $summary['max_downstream_level'],
            'max_inflow_rate'     => $summary['max_inflow_rate'],
            'max_outflow_rate'    => $summary['max_outflow_rate'],
            'total_energy'        => $summary['total_energy'],
            'total_discharge'     => $summary['total_discharge'],
            'anomaly_count'       => $summary['anomaly_count'],
        ]);

        $result->update(['status' => 'completed', 'end_time' => now(), 'summary' => $summary]);

        broadcast(new SimulationProgress(
            $this->taskNo,
            100,
            [
                'upstream_level'   => $maxUp,
                'downstream_level' => $maxDown,
                'inflow_rate'      => $maxIn,
                'outflow_rate'     => $maxOut,
                'gate_opening'     => $maxGate,
                'power_output'     => $basePower,
            ],
            $anomalies,
            'completed',
        ));
    }
}

<?php

namespace App\Console\Commands;

use App\Services\LX\EdgeService;
use Illuminate\Console\Command;

class SimulateMonitorData extends Command
{
    protected $signature   = 'simulate:monitor
                            {--reservoir=1 : 水库ID}
                            {--edge=1 : 边缘节点ID}
                            {--interval=5 : 上报间隔（秒）}
                            {--count=0 : 上报次数，0=无限循环}
                            {--batch=10 : 每次上报条数}';

    protected $description = '模拟边缘端上报实时监测数据';

    public function handle(): int
    {
        $reservoirId = (int) $this->option('reservoir');
        $edgeNodeId  = (int) $this->option('edge');
        $interval    = (int) $this->option('interval');
        $maxCount    = (int) $this->option('count');
        $batchSize   = (int) $this->option('batch');
        $service     = new EdgeService();
        $counter     = 0;

        // 初始基准值（向家坝典型值）
        $base = [
            'upstream_level'   => 370.0,
            'downstream_level' => 280.0,
            'inflow_rate'      => 3500.0,
            'outflow_rate'     => 3200.0,
            'gate_opening'     => 35.0,
            'power_output'     => 1500.0,
            'cumulative_energy'=> 125000.0,
        ];

        $this->info("开始模拟监测数据上报（水库={$reservoirId} 节点={$edgeNodeId} 间隔={$interval}s 批量={$batchSize}条）");

        while ($maxCount === 0 || $counter < $maxCount) {
            $counter++;
            $data = [];

            for ($i = 0; $i < $batchSize; $i++) {
                $now = now()->subSeconds($batchSize - $i);
                $data[] = $this->generateFrame($base, $now);
            }

            $result = $service->reportMonitoringData([
                'reservoir_id' => $reservoirId,
                'edge_node_id' => $edgeNodeId,
                'data'         => $data,
            ]);

            $latest = end($data);
            $this->info("[{$counter}/" . ($maxCount ?: '∞') . "] 上报 {$result['inserted']} 条 | "
                . "水位={$latest['upstream_level']}m 流量={$latest['inflow_rate']}m³/s 功率={$latest['power_output']}kW");

            if ($maxCount === 0 || $counter < $maxCount) {
                sleep($interval);
            }
        }

        $this->info('模拟完成');
        return 0;
    }

    private function generateFrame(array &$base, $timestamp): array
    {
        // 在基准值上加入 ±2%~5% 的随机波动
        $base['upstream_level']   = $this->fluctuate($base['upstream_level'], 1);
        $base['downstream_level'] = $this->fluctuate($base['downstream_level'], 0.5);
        $base['inflow_rate']      = $this->fluctuate($base['inflow_rate'], 50);
        $base['outflow_rate']     = $this->fluctuate($base['outflow_rate'], 50);
        $base['gate_opening']     = max(0, min(100, $this->fluctuate($base['gate_opening'], 2)));
        $base['power_output']     = $this->fluctuate($base['power_output'], 20);
        $base['cumulative_energy'] += mt_rand(1, 10) / 10;

        return [
            'timestamp'        => $timestamp->toISOString(),
            'upstream_level'   => round($base['upstream_level'], 3),
            'downstream_level' => round($base['downstream_level'], 3),
            'water_head'       => round($base['upstream_level'] - $base['downstream_level'], 3),
            'inflow_rate'      => round($base['inflow_rate'], 3),
            'outflow_rate'     => round($base['outflow_rate'], 3),
            'gate_opening'     => round($base['gate_opening'], 2),
            'power_output'     => round($base['power_output'], 3),
            'cumulative_energy'=> round($base['cumulative_energy'], 3),
        ];
    }

    private function fluctuate(float $value, float $range): float
    {
        return $value + (mt_rand(-100, 100) / 100) * $range * (mt_rand(1, 5) / 10);
    }
}

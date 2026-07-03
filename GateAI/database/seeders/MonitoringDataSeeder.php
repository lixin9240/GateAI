<?php

namespace Database\Seeders;

use App\Models\EdgeNode;
use App\Models\MonitoringData;
use Illuminate\Database\Seeder;

class MonitoringDataSeeder extends Seeder
{
    /**
     * 生成 10 条典型监测数据，覆盖各水库
     */
    public function run(): void
    {
        $now = now()->setSeconds(0);

        $reservoirs = \App\Models\Reservoir::pluck('id', 'code');

        $records = [
            // 三峡水库 —— 高水位大流量
            [
                'upstream_level'    => 174.800,
                'downstream_level'  => 68.500,
                'water_head'        => 106.300,
                'inflow_rate'       => 12500.000,
                'outflow_rate'      => 12200.000,
                'gate_opening'      => 65.50,
                'power_output'      => 19500.000,
                'cumulative_energy' => 468000.000,
            ],
            // 三峡水库 —— 低水位小流量
            [
                'upstream_level'    => 173.200,
                'downstream_level'  => 67.800,
                'water_head'        => 105.400,
                'inflow_rate'       => 9800.000,
                'outflow_rate'      => 9500.000,
                'gate_opening'      => 52.00,
                'power_output'      => 16200.000,
                'cumulative_energy' => 468300.000,
            ],
            // 溪洛渡水库 —— 高峰发电
            [
                'upstream_level'    => 599.200,
                'downstream_level'  => 377.500,
                'water_head'        => 221.700,
                'inflow_rate'       => 7200.000,
                'outflow_rate'      => 7100.000,
                'gate_opening'      => 70.00,
                'power_output'      => 13500.000,
                'cumulative_energy' => 324000.000,
            ],
            // 溪洛渡水库 —— 低谷
            [
                'upstream_level'    => 597.800,
                'downstream_level'  => 375.200,
                'water_head'        => 222.600,
                'inflow_rate'       => 5800.000,
                'outflow_rate'      => 5600.000,
                'gate_opening'      => 45.00,
                'power_output'      => 9800.000,
                'cumulative_energy' => 324200.000,
            ],
            // 向家坝水库 —— 正常发电
            [
                'upstream_level'    => 379.500,
                'downstream_level'  => 269.000,
                'water_head'        => 110.500,
                'inflow_rate'       => 4500.000,
                'outflow_rate'      => 4400.000,
                'gate_opening'      => 60.00,
                'power_output'      => 6500.000,
                'cumulative_energy' => 156000.000,
            ],
            // 向家坝水库 —— 低负荷
            [
                'upstream_level'    => 378.200,
                'downstream_level'  => 268.000,
                'water_head'        => 110.200,
                'inflow_rate'       => 3800.000,
                'outflow_rate'      => 3700.000,
                'gate_opening'      => 42.00,
                'power_output'      => 5200.000,
                'cumulative_energy' => 156150.000,
            ],
            // 上游模拟水库 —— 正常
            [
                'upstream_level'    => 3.050,
                'downstream_level'  => 1.520,
                'water_head'        => 1.530,
                'inflow_rate'       => 0.320,
                'outflow_rate'      => 0.300,
                'gate_opening'      => 48.00,
                'power_output'      => 0.220,
                'cumulative_energy' => 5.280,
            ],
            // 上游模拟水库 —— 闸门大开
            [
                'upstream_level'    => 2.880,
                'downstream_level'  => 1.650,
                'water_head'        => 1.230,
                'inflow_rate'       => 0.350,
                'outflow_rate'      => 0.420,
                'gate_opening'      => 82.00,
                'power_output'      => 0.180,
                'cumulative_energy' => 5.290,
            ],
            // 下游模拟水库 —— 正常
            [
                'upstream_level'    => 1.820,
                'downstream_level'  => 0.820,
                'water_head'        => 1.000,
                'inflow_rate'       => 0.260,
                'outflow_rate'      => 0.240,
                'gate_opening'      => 42.00,
                'power_output'      => 0.110,
                'cumulative_energy' => 2.640,
            ],
            // 下游模拟水库 —— 高水位
            [
                'upstream_level'    => 1.950,
                'downstream_level'  => 0.950,
                'water_head'        => 1.000,
                'inflow_rate'       => 0.300,
                'outflow_rate'      => 0.280,
                'gate_opening'      => 55.00,
                'power_output'      => 0.130,
                'cumulative_energy' => 2.650,
            ],
        ];

        // 时间均匀分布在过去 2 小时内
        $baseTime = $now->copy()->subHours(2);
        $interval = 2 * 60 / count($records);   // 约 12 分钟一条

        $insertData = [];
        foreach ($records as $i => $rec) {
            // 轮询分配水库
            $reservoirCode = match (true) {
                $i < 2  => 'SX_RES_001',
                $i < 4  => 'XLD_RES_001',
                $i < 6  => 'XJB_RES_001',
                $i < 8  => 'RES-UP-001',
                default => 'RES-DOWN-001',
            };
            $reservoirId = $reservoirs[$reservoirCode] ?? null;
            if (!$reservoirId) {
                continue;
            }

            $edgeNode = EdgeNode::where('reservoir_id', $reservoirId)
                ->where('status', 'online')
                ->first();
            if (!$edgeNode) {
                continue;
            }

            $ts = $baseTime->copy()->addMinutes((int) round($i * $interval));

            $insertData[] = array_merge($rec, [
                'timestamp'    => $ts,
                'reservoir_id' => $reservoirId,
                'edge_node_id' => $edgeNode->id,
                'data_source'  => 'simulation',
                'is_anomaly'   => 0,
                'created_at'   => $now,
            ]);
        }

        MonitoringData::insert($insertData);
        $this->command?->info('已生成 ' . count($insertData) . ' 条监测数据');
    }
}

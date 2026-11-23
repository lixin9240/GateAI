<?php

namespace Database\Seeders;

use App\Models\EdgeNode;
use App\Models\MonitoringData;
use App\Models\Reservoir;
use Illuminate\Database\Seeder;

/**
 * 近 15 天监测数据 —— 供历史查询接口使用
 *
 * 按 30 分钟粒度生成，五个水库各约 720 条，总量约 3600 条。
 * data_source 使用 seed 标记，重复执行时只清理种子数据，不影响真实数据。
 */
class RecentMonitoringDataSeeder extends Seeder
{
    private const DAYS = 15;

    private const STEP_MINUTES = 30;

    public function run(): void
    {
        $now = now()->startOfHour();
        $start = $now->copy()->subDays(self::DAYS);

        MonitoringData::where('data_source', 'seed')
            ->whereBetween('timestamp', [$start, $now])
            ->delete();

        $reservoirs = Reservoir::query()->get()->keyBy('code');
        $stepsPerDay = intdiv(1440, self::STEP_MINUTES);
        $totalSteps = self::DAYS * $stepsPerDay;

        $rows = [];
        $inserted = 0;

        foreach ($this->profiles() as $code => $profile) {
            $reservoir = $reservoirs->get($code);
            if (! $reservoir) {
                $this->command?->warn("跳过 {$code}：水库不存在");
                continue;
            }

            $edgeNode = EdgeNode::where('reservoir_id', $reservoir->id)
                ->where('status', 'online')
                ->orderBy('id')
                ->first()
                ?? EdgeNode::where('reservoir_id', $reservoir->id)->orderBy('id')->first();

            if (! $edgeNode) {
                $this->command?->warn("跳过 {$code}：没有可用边缘节点");
                continue;
            }

            $cumulative = $profile['cumulative_energy'];
            $cursor = $start->copy();

            for ($step = 0; $step <= $totalSteps; $step++) {
                $hour = (int) $cursor->format('G');
                $day = $step / $stepsPerDay;

                $diurnal = sin((($hour - 6) / 24) * 2 * M_PI);
                $cycle = sin(($day / self::DAYS) * 2 * M_PI);
                $noise = mt_rand(-1000, 1000) / 1000;

                $upstream = $profile['upstream']
                    + $profile['up_amp'] * (0.65 * $diurnal + 0.35 * $cycle)
                    + $noise * $profile['noise'];

                $downstream = $profile['downstream']
                    + $profile['down_amp'] * (0.55 * $diurnal - 0.25 * $cycle)
                    + $noise * $profile['noise'] * 0.4;

                $inflow = $profile['inflow']
                    * (1 + 0.14 * $diurnal + 0.08 * $cycle + 0.03 * $noise);

                $outflow = $profile['outflow']
                    * (1 + 0.12 * $diurnal + 0.06 * $cycle + 0.03 * $noise);

                $gate = max(0, min(100, $profile['gate'] + 12 * $diurnal + 4 * $noise));

                $power = max(
                    0,
                    $profile['power'] * (0.82 + 0.15 * $diurnal + 0.05 * $cycle + 0.03 * $noise)
                );

                $cumulative += $power * (self::STEP_MINUTES / 60);

                $rows[] = [
                    'timestamp'         => $cursor->toDateTimeString(),
                    'reservoir_id'      => $reservoir->id,
                    'upstream_level'    => round($upstream, 3),
                    'downstream_level'  => round($downstream, 3),
                    'water_head'        => round($upstream - $downstream, 3),
                    'inflow_rate'       => round($inflow, 3),
                    'outflow_rate'      => round($outflow, 3),
                    'gate_opening'      => round($gate, 2),
                    'power_output'      => round($power, 3),
                    'cumulative_energy' => round($cumulative, 3),
                    'edge_node_id'      => $edgeNode->id,
                    'data_source'       => 'seed',
                    'is_anomaly'        => 0,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ];

                if (count($rows) >= 500) {
                    MonitoringData::insert($rows);
                    $inserted += count($rows);
                    $rows = [];
                }

                $cursor->addMinutes(self::STEP_MINUTES);
            }
        }

        if ($rows !== []) {
            MonitoringData::insert($rows);
            $inserted += count($rows);
        }

        $this->command?->info("已生成近 " . self::DAYS . " 天监测数据：{$inserted} 条");
    }

    /**
     * 各水库基准值，参考 MonitoringDataSeeder 的典型工况
     */
    private function profiles(): array
    {
        return [
            'SX_RES_001' => [
                'upstream'          => 174.000,
                'downstream'        => 68.200,
                'inflow'            => 11150.000,
                'outflow'           => 10850.000,
                'gate'              => 58.75,
                'power'             => 17850.000,
                'cumulative_energy' => 468150.000,
                'up_amp'            => 0.800,
                'down_amp'          => 0.350,
                'noise'             => 0.080,
            ],
            'XLD_RES_001' => [
                'upstream'          => 598.500,
                'downstream'        => 376.350,
                'inflow'            => 6500.000,
                'outflow'           => 6350.000,
                'gate'              => 57.50,
                'power'             => 11650.000,
                'cumulative_energy' => 324100.000,
                'up_amp'            => 0.600,
                'down_amp'          => 0.300,
                'noise'             => 0.060,
            ],
            'XJB_RES_001' => [
                'upstream'          => 378.850,
                'downstream'        => 268.500,
                'inflow'            => 4150.000,
                'outflow'           => 4050.000,
                'gate'              => 51.00,
                'power'             => 5850.000,
                'cumulative_energy' => 156075.000,
                'up_amp'            => 0.400,
                'down_amp'          => 0.200,
                'noise'             => 0.050,
            ],
            'RES-UP-001' => [
                'upstream'          => 2.965,
                'downstream'        => 1.585,
                'inflow'            => 0.335,
                'outflow'           => 0.360,
                'gate'              => 65.00,
                'power'             => 0.200,
                'cumulative_energy' => 5.285,
                'up_amp'            => 0.060,
                'down_amp'          => 0.030,
                'noise'             => 0.010,
            ],
            'RES-DOWN-001' => [
                'upstream'          => 1.885,
                'downstream'        => 0.885,
                'inflow'            => 0.280,
                'outflow'           => 0.260,
                'gate'              => 48.50,
                'power'             => 0.120,
                'cumulative_energy' => 2.645,
                'up_amp'            => 0.040,
                'down_amp'          => 0.020,
                'noise'             => 0.008,
            ],
        ];
    }
}

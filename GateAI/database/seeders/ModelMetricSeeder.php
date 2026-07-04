<?php

namespace Database\Seeders;

use App\Models\EdgeNode;
use App\Models\ModelMetric;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModelMetricSeeder extends Seeder
{
    /**
     * 模型三维评判体系 —— 测试种子数据
     * 只生成 10 条，用于接口快速验证
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        ModelMetric::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $reservoirId = 1;
        $edgeNode    = EdgeNode::where('reservoir_id', $reservoirId)->first();
        $edgeNodeId  = $edgeNode?->id ?? 1;
        $endTime     = now()->startOfHour();

        for ($i = 9; $i >= 0; $i--) {
                $metricTime = $endTime->copy()->subHours($i);

                // 基础分 + 随机波动 + 时间衰减（越老的数据略低）
                $prediction = round(min(0.95, max(0.45, 0.75 + sin($i / 5) * 0.1 + (mt_rand() / mt_getrandmax() - 0.5) * 0.15)), 4);
                $decision   = round(min(0.95, max(0.45, 0.72 + cos($i / 7) * 0.1 + (mt_rand() / mt_getrandmax() - 0.5) * 0.15)), 4);
                $compliance = round(min(0.95, max(0.45, 0.78 + sin($i / 6) * 0.08 + (mt_rand() / mt_getrandmax() - 0.5) * 0.12)), 4);

                $overall = round(0.40 * $prediction + 0.35 * $decision + 0.25 * $compliance, 4);
                $grade   = match (true) {
                    $overall >= 0.85 => 'S',
                    $overall >= 0.70 => 'A',
                    $overall >= 0.55 => 'B',
                    $overall >= 0.40 => 'C',
                    default          => 'D',
                };

                $dist = $this->randomDistribution();

                ModelMetric::create([
                    'edge_node_id'            => $edgeNodeId,
                    'reservoir_id'            => $reservoirId,
                    'metric_time'             => $metricTime,

                    'water_level_mae_24h'     => round(0.01 + mt_rand() / mt_getrandmax() * 0.50, 4),
                    'flow_mae_24h'            => round(1 + mt_rand() / mt_getrandmax() * 100, 2),
                    'physics_correction_rate' => round(mt_rand() / mt_getrandmax() * 0.30, 4),
                    'trend_accuracy'          => round(0.60 + mt_rand() / mt_getrandmax() * 0.40, 4),
                    'prediction_score'        => $prediction,

                    'safety_override_rate'    => round(0.70 + mt_rand() / mt_getrandmax() * 0.30, 4),
                    'decision_level_dist'     => $dist,
                    'shadow_risk_pass_rate'   => round(0.70 + mt_rand() / mt_getrandmax() * 0.30, 4),
                    'smooth_filter_rate'      => round(0.50 + mt_rand() / mt_getrandmax() * 0.50, 4),
                    'decision_score'          => $decision,

                    'avg_physics_violation'   => round(0.001 + mt_rand() / mt_getrandmax() * 0.30, 4),
                    'gate_limit_touch_rate'   => round(mt_rand() / mt_getrandmax() * 0.20, 4),
                    'rate_limit_exceed_rate'  => round(mt_rand() / mt_getrandmax() * 0.20, 4),
                    'compliance_score'        => $compliance,

                    'overall_score'           => $overall,
                    'health_grade'            => $grade,
                    'created_at'              => $metricTime,
                ]);
            }
    }

    /**
     * 生成一个和为 1 的决策等级分布
     */
    private function randomDistribution(): array
    {
        $parts = [
            mt_rand(10, 60),
            mt_rand(10, 40),
            mt_rand(5, 30),
            mt_rand(0, 15),
        ];
        $total = array_sum($parts);

        return [
            'L3'       => round($parts[0] / $total, 4),
            'L2'       => round($parts[1] / $total, 4),
            'L1'       => round($parts[2] / $total, 4),
            'OVERRIDE' => round($parts[3] / $total, 4),
        ];
    }
}

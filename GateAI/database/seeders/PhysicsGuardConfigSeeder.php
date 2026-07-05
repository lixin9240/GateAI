<?php

namespace Database\Seeders;

use App\Models\PhysicsGuardConfig;
use App\Models\Reservoir;
use Illuminate\Database\Seeder;

class PhysicsGuardConfigSeeder extends Seeder
{
    /**
     * 为活跃水库创建默认物理防护配置（替代 Python deploy_config.json 硬编码值）。
     * 配置参数按水库类型差异化：
     *   - seasonal 型（三峡/溪洛渡）沿用通用默认值
     *   - daily_regulation 型（向家坝）死区更保守（日调节动作频繁）
     *   - simulation 型（模拟水库）使用缩小版参数
     */
    public function run(): void
    {
        $configs = [
            [
                'name'   => '三峡水库',
                'config' => [
                    'upstream_danger'       => 190.00,
                    'upstream_emergency'    => 193.00,
                    'upstream_warning'      => 188.00,
                    'upstream_min'          => 167.00,
                    'ideal_min'             => 178.00,
                    'ideal_max'             => 188.00,
                    'downstream_danger'     => 128.00,
                    'downstream_max'        => 130.00,
                    'downstream_min'        => 115.00,
                    'eco_flow_min'          => 20.00,
                    'reservoir_area'        => 15000000,
                    'max_level_change_per_hour' => 2.00,
                    'shadow_lookahead_steps' => 3,
                    'shadow_danger_offset'   => 3.00,
                    'deadband_percent'       => 0.02,
                    'max_rate_per_hour'      => 0.10,
                    'fusion_l3_confidence'   => 0.70,
                    'fusion_l3_risk'         => 0.30,
                    'fusion_l2_confidence'   => 0.50,
                    'fusion_l2_risk'         => 0.10,
                    'gate_max_discharge'     => ['300', '200', '250'],
                ],
            ],
            [
                'name'   => '溪洛渡水库',
                'config' => [
                    'upstream_danger'       => 190.00,
                    'upstream_emergency'    => 193.00,
                    'upstream_warning'      => 188.00,
                    'upstream_min'          => 167.00,
                    'ideal_min'             => 178.00,
                    'ideal_max'             => 188.00,
                    'downstream_danger'     => 128.00,
                    'downstream_max'        => 130.00,
                    'downstream_min'        => 115.00,
                    'eco_flow_min'          => 20.00,
                    'reservoir_area'        => 15000000,
                    'max_level_change_per_hour' => 2.00,
                    'shadow_lookahead_steps' => 3,
                    'shadow_danger_offset'   => 3.00,
                    'deadband_percent'       => 0.02,
                    'max_rate_per_hour'      => 0.10,
                    'fusion_l3_confidence'   => 0.70,
                    'fusion_l3_risk'         => 0.30,
                    'fusion_l2_confidence'   => 0.50,
                    'fusion_l2_risk'         => 0.10,
                    'gate_max_discharge'     => ['300', '200', '250'],
                ],
            ],
            [
                'name'   => '向家坝水库',
                'config' => [
                    'upstream_danger'       => 190.00,
                    'upstream_emergency'    => 193.00,
                    'upstream_warning'      => 188.00,
                    'upstream_min'          => 167.00,
                    'ideal_min'             => 178.00,
                    'ideal_max'             => 188.00,
                    'downstream_danger'     => 128.00,
                    'downstream_max'        => 130.00,
                    'downstream_min'        => 115.00,
                    'eco_flow_min'          => 20.00,
                    'reservoir_area'        => 15000000,
                    'max_level_change_per_hour' => 2.00,
                    'shadow_lookahead_steps' => 3,
                    'shadow_danger_offset'   => 3.00,
                    // 日调节型水库，死区更保守（减少频繁微小动作）
                    'deadband_percent'       => 0.03,
                    'max_rate_per_hour'      => 0.08,
                    'fusion_l3_confidence'   => 0.70,
                    'fusion_l3_risk'         => 0.30,
                    'fusion_l2_confidence'   => 0.50,
                    'fusion_l2_risk'         => 0.10,
                    'gate_max_discharge'     => ['300', '200', '250'],
                ],
            ],
            [
                'name'   => '上游模拟水库',
                'config' => [
                    'upstream_danger'       => 190.00,
                    'upstream_emergency'    => 193.00,
                    'upstream_warning'      => 188.00,
                    'upstream_min'          => 167.00,
                    'ideal_min'             => 178.00,
                    'ideal_max'             => 188.00,
                    'downstream_danger'     => 128.00,
                    'downstream_max'        => 130.00,
                    'downstream_min'        => 115.00,
                    'eco_flow_min'          => 20.00,
                    'reservoir_area'        => 15000000,
                    'max_level_change_per_hour' => 2.00,
                    'shadow_lookahead_steps' => 3,
                    'shadow_danger_offset'   => 3.00,
                    'deadband_percent'       => 0.02,
                    'max_rate_per_hour'      => 0.10,
                    'fusion_l3_confidence'   => 0.70,
                    'fusion_l3_risk'         => 0.30,
                    'fusion_l2_confidence'   => 0.50,
                    'fusion_l2_risk'         => 0.10,
                    'gate_max_discharge'     => ['300', '200', '250'],
                ],
            ],
            [
                'name'   => '下游模拟水库',
                'config' => [
                    'upstream_danger'       => 190.00,
                    'upstream_emergency'    => 193.00,
                    'upstream_warning'      => 188.00,
                    'upstream_min'          => 167.00,
                    'ideal_min'             => 178.00,
                    'ideal_max'             => 188.00,
                    'downstream_danger'     => 128.00,
                    'downstream_max'        => 130.00,
                    'downstream_min'        => 115.00,
                    'eco_flow_min'          => 20.00,
                    'reservoir_area'        => 15000000,
                    'max_level_change_per_hour' => 2.00,
                    'shadow_lookahead_steps' => 3,
                    'shadow_danger_offset'   => 3.00,
                    'deadband_percent'       => 0.02,
                    'max_rate_per_hour'      => 0.10,
                    'fusion_l3_confidence'   => 0.70,
                    'fusion_l3_risk'         => 0.30,
                    'fusion_l2_confidence'   => 0.50,
                    'fusion_l2_risk'         => 0.10,
                    'gate_max_discharge'     => ['300', '200', '250'],
                ],
            ],
        ];

        $now = now();

        foreach ($configs as $item) {
            $reservoir = Reservoir::where('name', $item['name'])->first();
            if (! $reservoir) {
                continue;
            }

            PhysicsGuardConfig::create(array_merge(
                $item['config'],
                [
                    'reservoir_id'   => $reservoir->id,
                    'config_version' => '1.0.0',
                    'is_active'      => 1,
                    'description'    => '初始默认配置（从 deploy_config.json 迁移）',
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]
            ));
        }
    }
}

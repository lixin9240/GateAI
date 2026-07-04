<?php

namespace Database\Seeders;

use App\Models\GateInterlockRule;
use Illuminate\Database\Seeder;

class GateInterlockRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'reservoir_id'       => null,
                'rule_code'          => 'spillway_intake_mutex',
                'rule_name'          => '泄洪-发电互斥',
                'description'        => '溢洪道开度 > 80% 时，发电引水闸 ≤ 50%，防止气蚀损坏水轮机',
                'enabled'            => true,
                'priority'           => 1,
                'trigger_conditions' => json_encode(['spillway_opening_gt' => 0.8]),
                'constraint_action'  => json_encode(['intake_max' => 0.5, 'action' => 'clamp']),
            ],
            [
                'reservoir_id'       => null,
                'rule_code'          => 'downstream_impact_protect',
                'rule_name'          => '下游冲击保护',
                'description'        => '任两闸门同时增开 > 30%，第三个闸门禁止同向动作，防止下游冲刷堤岸',
                'enabled'            => true,
                'priority'           => 2,
                'trigger_conditions' => json_encode(['two_gates_increase_gt' => 0.3]),
                'constraint_action'  => json_encode(['third_gate_lock' => true, 'action' => 'freeze']),
            ],
            [
                'reservoir_id'       => null,
                'rule_code'          => 'symmetry_constraint',
                'rule_name'          => '对称性约束',
                'description'        => '溢洪道与泄洪洞开度差 > 40% 时强制对齐至差值 ≤ 40%，防止偏流冲刷一侧坝体',
                'enabled'            => true,
                'priority'           => 3,
                'trigger_conditions' => json_encode(['opening_diff_gt' => 0.4]),
                'constraint_action'  => json_encode(['max_diff' => 0.4, 'action' => 'clamp']),
            ],
            [
                'reservoir_id'       => null,
                'rule_code'          => 'min_discharge_guarantee',
                'rule_name'          => '最小下泄保障',
                'description'        => '三闸门总开度 < 5% 时禁止同时全关，保证下游不断流，维持生态基流',
                'enabled'            => true,
                'priority'           => 4,
                'trigger_conditions' => json_encode(['total_opening_lt' => 0.05]),
                'constraint_action'  => json_encode(['min_total' => 0.05, 'action' => 'clamp']),
            ],
        ];

        foreach ($rules as $rule) {
            GateInterlockRule::create($rule);
        }
    }
}

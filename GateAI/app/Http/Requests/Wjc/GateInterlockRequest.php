<?php

namespace App\Http\Requests\Wjc;

use Illuminate\Foundation\Http\FormRequest;

class GateInterlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->method();
        $path = $this->path();

        // 更新规则
        if ($method === 'PUT' && str_contains($path, 'rules')) {
            return [
                'rule_name'         => 'nullable|string|max:100',
                'description'       => 'nullable|string|max:255',
                'enabled'           => 'nullable|boolean',
                'priority'          => 'nullable|integer|min:0',
                'trigger_conditions' => 'nullable|array',
                'constraint_action'  => 'nullable|array',
            ];
        }

        // 启用/禁用
        if ($method === 'POST' && str_contains($path, 'toggle')) {
            return [
                'enabled' => 'required|boolean',
            ];
        }

        // 边缘端上报
        if (str_contains($path, 'gate-interlock-logs') && $method === 'POST') {
            return [
                'reservoir_id'         => 'required|integer|exists:reservoirs,id',
                'rule_id'              => 'required|integer|exists:gate_interlock_rules,id',
                'decision_id'          => 'nullable|integer|exists:dispatch_decisions,id',
                'trigger_time'         => 'nullable|date',
                'gate1_opening_before' => 'required|numeric|min:0|max:1',
                'gate2_opening_before' => 'required|numeric|min:0|max:1',
                'gate3_opening_before' => 'required|numeric|min:0|max:1',
                'upstream_level'       => 'required|numeric',
                'downstream_level'     => 'required|numeric',
                'inflow_rate'          => 'required|numeric',
                'gate1_opening_after'  => 'required|numeric|min:0|max:1',
                'gate2_opening_after'  => 'required|numeric|min:0|max:1',
                'gate3_opening_after'  => 'required|numeric|min:0|max:1',
                'action_detail'        => 'nullable|array',
            ];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.exists'       => '关联的水库不存在',
            'rule_id.exists'            => '关联的规则不存在',
            'decision_id.exists'        => '关联的调度决策不存在',
            'trigger_conditions.json'   => '触发条件格式不正确',
            'constraint_action.json'    => '约束动作格式不正确',
        ];
    }
}

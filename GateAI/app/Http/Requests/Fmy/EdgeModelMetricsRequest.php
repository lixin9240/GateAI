<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class EdgeModelMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'edge_node_id'              => 'required|integer|exists:edge_nodes,id',
            'reservoir_id'              => 'required|integer|exists:reservoirs,id',
            'metric_time'               => 'required|date_format:Y-m-d H:i:s',

            // 维度一：预测准确性
            'water_level_mae_24h'       => 'nullable|numeric|min:0|max:9999',
            'flow_mae_24h'              => 'nullable|numeric|min:0|max:9999',
            'physics_correction_rate'   => 'nullable|numeric|between:0,1',
            'trend_accuracy'            => 'nullable|numeric|between:0,1',
            'prediction_score'          => 'nullable|numeric|between:0,1',

            // 维度二：决策可靠性
            'safety_override_rate'      => 'nullable|numeric|between:0,1',
            'decision_level_dist'          => 'nullable|array',
            'decision_level_dist.L3'       => 'nullable|numeric|between:0,1',
            'decision_level_dist.L2'       => 'nullable|numeric|between:0,1',
            'decision_level_dist.L1'       => 'nullable|numeric|between:0,1',
            'decision_level_dist.OVERRIDE' => 'nullable|numeric|between:0,1',
            'shadow_risk_pass_rate'     => 'nullable|numeric|between:0,1',
            'smooth_filter_rate'        => 'nullable|numeric|between:0,1',
            'decision_score'            => 'nullable|numeric|between:0,1',

            // 维度三：物理合规性
            'avg_physics_violation'     => 'nullable|numeric|min:0|max:9999',
            'gate_limit_touch_rate'     => 'nullable|numeric|between:0,1',
            'rate_limit_exceed_rate'    => 'nullable|numeric|between:0,1',
            'compliance_score'          => 'nullable|numeric|between:0,1',

            // 综合
            'overall_score'             => 'nullable|numeric|between:0,1',
            'health_grade'              => 'nullable|in:S,A,B,C,D',
        ];
    }
}

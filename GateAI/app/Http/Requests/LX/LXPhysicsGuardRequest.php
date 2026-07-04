<?php
// 物理防护配置请求
namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXPhysicsGuardRequest extends FormRequest
{
    public function rules(): array
    {
        // 更新物理防护配置
        if ($this->isMethod('put') || $this->isMethod('post')) {
            return [
                'upstream_danger'           => 'numeric|min:0|nullable',//上游危险值
                'upstream_emergency'        => 'numeric|min:0|nullable',//上游紧急值
                'upstream_warning'          => 'numeric|min:0|nullable',//上游警告值
                'upstream_min'              => 'numeric|min:0|nullable',//上游最小值
                'ideal_min'                 => 'numeric|min:0|nullable',//理想最小值
                'ideal_max'                 => 'numeric|min:0|nullable',//理想最大值
                'downstream_danger'         => 'numeric|min:0|nullable',//下游危险值
                'downstream_max'            => 'numeric|min:0|nullable',//下游最大值
                'downstream_min'            => 'numeric|min:0|nullable',//下游最小值
                'eco_flow_min'              => 'numeric|min:0|nullable',//生态流量最小值
                'reservoir_area'            => 'integer|min:1|nullable',//水库面积
                'max_level_change_per_hour' => 'numeric|min:0|nullable',//最大水位变化率
                'shadow_lookahead_steps'    => 'integer|min:1|max:10|nullable',//前瞻步数
                'shadow_danger_offset'      => 'numeric|min:0|nullable',//阴影危险偏移量
                'deadband_percent'          => 'numeric|min:0|max:1|nullable',//死区百分比
                'max_rate_per_hour'         => 'numeric|min:0|max:1|nullable',//最大流量率
                'fusion_l3_confidence'      => 'numeric|min:0|max:1|nullable',//熔断阈值
                'fusion_l3_risk'            => 'numeric|min:0|max:1|nullable',//风险值
                'fusion_l2_confidence'      => 'numeric|min:0|max:1|nullable',//置信度
                'fusion_l2_risk'            => 'numeric|min:0|max:1|nullable',//风险值
                'gate_max_discharge'        => 'array|nullable',
                'description'               => 'string|max:255|nullable',
            ];
        }

        // 克隆
        if ($this->route()?->getActionMethod() === 'cloneConfig') {
            return [
                'from_reservoir_id' => 'required|integer|exists:reservoirs,id',
                'to_reservoir_id'   => 'required|integer|exists:reservoirs,id|different:from_reservoir_id',
            ];
        }

        return [
            'reservoir_id' => 'integer|exists:reservoirs,id|nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_area.integer'            => '水库面积必须为整数',
            'shadow_lookahead_steps.min'        => '前瞻步数至少为1',
            'shadow_lookahead_steps.max'        => '前瞻步数最多为10',
            'deadband_percent.between'          => '死区百分比必须在0~1之间',
            'fusion_l3_confidence.max'          => '熔断阈值必须在0~1之间',
            'from_reservoir_id.required'        => '源水库ID不能为空',
            'to_reservoir_id.different'         => '目标水库不能与源水库相同',
        ];
    }
}

<?php
// 仿真场景请求
namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXSimulationRequest extends FormRequest
{
    public function rules(): array
    {
        $route = $this->route()->getName();

        if ($route === 'simulation.start') {
            return [
                'scenario_id'              => 'required|exists:simulation_scenarios,id',// 仿真场景ID
                'model_id'                 => 'required|exists:settings_models,id',// 模型ID
                'reservoir_id'             => 'required|exists:reservoirs,id',// 水库ID
                'duration'                 => 'integer|min:60',// 仿真时间
                'speed'                    => 'numeric|min:0.1|max:10.0',// 加速倍率
                'params'                   => 'array|nullable',// 参数
                'params.initial_water_level' => 'numeric|nullable',// 初始水位
                'params.inflow_rate'         => 'numeric|nullable',// 入流率
                'params.gate_opening'        => 'numeric|min:0|max:100|nullable',// 门开度
            ];
        }
        // 仿真报告规则
        if ($route === 'simulation.report') {
            return [
                'report_type'     => 'required|in:full,summary,anomaly',// 报告类型
                'format'          => 'in:pdf,html',// 格式
                'include_charts'  => 'boolean',// 是否包含图表
            ];
        }

        // result query
        return [
            'metric_type' => 'string|nullable',// 指标类型
            'aggregation' => 'in:raw,avg,max,min|nullable',// 聚合类型
        ];
    }

    public function messages(): array
    {
        return [
            'scenario_id.required'      => '仿真场景ID不能为空',
            'scenario_id.exists'        => '仿真场景不存在',
            'model_id.required'         => '模型ID不能为空',
            'model_id.exists'           => '模型不存在',
            'reservoir_id.required'     => '水库ID不能为空',
            'reservoir_id.exists'       => '水库不存在',
            'report_type.required'      => '报告类型不能为空',
            'report_type.in'            => '报告类型仅支持 full / summary / anomaly',
            'speed.max'                 => '加速倍率最大不可超过10倍',
        ];
    }
}

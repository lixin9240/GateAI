<?php

namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXSimulationRequest extends FormRequest
{
    public function rules(): array
    {
        $route = $this->route()->getName();

        if ($route === 'simulation.start') {
            return [
                'scenario_id'              => 'required|exists:simulation_scenarios,id',
                'model_id'                 => 'required|exists:settings_models,id',
                'reservoir_id'             => 'required|exists:reservoirs,id',
                'duration'                 => 'integer|min:60',
                'speed'                    => 'numeric|min:0.1|max:10.0',
                'params'                   => 'array|nullable',
                'params.initial_water_level' => 'numeric|nullable',
                'params.inflow_rate'         => 'numeric|nullable',
                'params.gate_opening'        => 'numeric|min:0|max:100|nullable',
            ];
        }

        if ($route === 'simulation.report') {
            return [
                'report_type'     => 'required|in:full,summary,anomaly',
                'format'          => 'in:pdf,html',
                'include_charts'  => 'boolean',
            ];
        }

        // result query
        return [
            'metric_type' => 'string|nullable',
            'aggregation' => 'in:raw,avg,max,min|nullable',
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

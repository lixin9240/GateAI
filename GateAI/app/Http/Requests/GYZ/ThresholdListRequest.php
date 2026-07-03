<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class ThresholdListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reservoir_id' => 'nullable|integer|min:1',
            'metric'       => 'nullable|string|in:upstream_level,downstream_level,inflow_rate,outflow_rate,gate_opening,power_output',
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.integer' => '水库ID必须是整数',
            'metric.in'           => '监控指标类型不合法',
        ];
    }
}

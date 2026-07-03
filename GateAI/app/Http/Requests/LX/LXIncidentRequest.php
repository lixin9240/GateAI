<?php

namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXIncidentRequest extends FormRequest
{
    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'incident_name'               => 'required|string|max:100',
                'description'                 => 'string|nullable',
                'severity'                    => 'required|in:low,medium,high,critical',
                'equipment_id'                => 'required|exists:equipment,id',
                'occurred_at'                 => 'required|date',
                'resolved_at'                 => 'date|nullable|after_or_equal:occurred_at',
                'raw_data'                    => 'required|array',
                'scenario_config'             => 'array|nullable',
                'scenario_config.name'        => 'string|max:100|nullable',
                'scenario_config.auto_run'    => 'boolean|nullable',
            ];
        }

        return [
            'page'         => 'integer|min:1',
            'page_size'    => 'integer|min:1|max:100',
            'reservoir_id' => 'exists:reservoirs,id|nullable',
            'severity'     => 'in:low,medium,high,critical|nullable',
            'start_time'   => 'date|nullable',
            'end_time'     => 'date|nullable|after_or_equal:start_time',
        ];
    }

    public function messages(): array
    {
        return [
            'incident_name.required' => '故障名称不能为空',
            'severity.required'      => '严重程度不能为空',
            'equipment_id.required'  => '关联设备ID不能为空',
            'occurred_at.required'   => '发生时间不能为空',
            'raw_data.required'      => '故障原始数据不能为空',
        ];
    }
}

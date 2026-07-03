<?php
// 故障复盘请求
namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXIncidentRequest extends FormRequest
{
    public function rules(): array
    {
        // 新增故障复盘规则
        if ($this->isMethod('post')) {
            return [
                'incident_name'               => 'required|string|max:100',// 故障名称
                'description'                 => 'string|nullable',// 故障描述
                'severity'                    => 'required|in:low,medium,high,critical',// 严重程度
                'equipment_id'                => 'required|exists:equipment,id',// 关联设备ID
                'occurred_at'                 => 'required|date',// 发生时间
                'resolved_at'                 => 'date|nullable|after_or_equal:occurred_at',// 解决时间
                'raw_data'                    => 'required|array',// 故障原始数据
                'scenario_config'             => 'array|nullable',// 场景配置
                'scenario_config.name'        => 'string|max:100|nullable',// 场景名称
                'scenario_config.auto_run'    => 'boolean|nullable',// 是否自动运行
            ];
        }

        return [
            'page'         => 'integer|min:1',// 页码
            'page_size'    => 'integer|min:1|max:100',// 每页数量
            'reservoir_id' => 'exists:reservoirs,id|nullable',// 水库ID
            'severity'     => 'in:low,medium,high,critical|nullable',// 严重程度
            'start_time'   => 'date|nullable',// 开始时间
            'end_time'     => 'date|nullable|after_or_equal:start_time',// 结束时间
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

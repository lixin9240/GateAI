<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WjcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // 告警确认
        if ($this->isMethod('PUT') && str_contains($this->path(), 'acknowledge')) {
            return [];
        }

        // 告警处置
        if ($this->isMethod('PUT') && str_contains($this->path(), 'dispose')) {
            return [
                'dispose_note' => 'required|string|max:500',
            ];
        }

        // 超限日志
        if ($this->isMethod('GET') && str_contains($this->path(), 'exceed-logs')) {
            return [
                'equipment_id' => 'nullable|integer',
                'start_time'   => 'nullable|date',
                'end_time'     => 'nullable|date|after_or_equal:start_time',
                'page_size'    => 'nullable|integer|min:1|max:100',
            ];
        }

        // 创建调度计划
        if ($this->isMethod('POST') && str_contains($this->path(), 'plans')) {
            return [
                'reservoir_id' => 'required|integer|exists:reservoirs,id',
                'plan_type'    => 'required|string|in:normal,flood,drought',
                'target_flow'  => 'required|numeric|min:0',
                'start_time'   => 'required|date|after:now',
                'end_time'     => 'required|date|after:start_time',
            ];
        }

        // 执行调度
        if ($this->isMethod('POST') && str_contains($this->path(), 'executions')) {
            return [
                'plan_id'      => 'required|integer|exists:dispatch_plans,id',
                'equipment_id' => 'required|integer|exists:equipment,id',
                'action_type'  => 'required|string|in:open,close,adjust',
                'target_value' => 'required|numeric|min:0',
            ];
        }

        // 调度计划列表
        if ($this->isMethod('GET') && str_contains($this->path(), 'plans')) {
            return [
                'reservoir_id' => 'nullable|integer',
                'status'       => 'nullable|string|in:pending,active,completed,cancelled',
                'page_size'    => 'nullable|integer|min:1|max:100',
            ];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'dispose_note.required'      => '处置备注不能为空',
            'end_time.after_or_equal'    => '结束时间必须大于或等于开始时间',
            'reservoir_id.required'      => '水库ID不能为空',
            'target_flow.required'       => '目标流量不能为空',
            'start_time.after'           => '开始时间必须晚于当前时间',
            'end_time.after'             => '结束时间必须晚于开始时间',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WjcRequest extends FormRequest
{
    /**
     * 确定用户是否有权进行此请求
     */
    public function authorize(): bool
    {
        return true; // 实际项目中可改为 auth()->check()
    }

    /**
     * 获取适用于请求的验证规则
     */
    public function rules(): array
    {
        // 根据请求路径返回不同的验证规则
        if ($this->isMethod('PUT') && str_contains($this->path(), 'acknowledge')) {
            return []; // 确认告警不需要额外参数
        }

        if ($this->isMethod('PUT') && str_contains($this->path(), 'dispose')) {
            return [
                'dispose_note' => 'required|string|max:500',
            ];
        }

        if ($this->isMethod('GET') && str_contains($this->path(), 'exceed-logs')) {
            return [
                'equipment_id' => 'nullable|integer',
                'start_time' => 'nullable|date',
                'end_time' => 'nullable|date|after_or_equal:start_time',
                'page_size' => 'nullable|integer|min:1|max:100',
            ];
        }

        return [];
    }

    /**
     * 自定义验证消息
     */
    public function messages(): array
    {
        return [
            'dispose_note.required' => '处置备注不能为空',
            'end_time.after_or_equal' => '结束时间必须大于或等于开始时间',
        ];
    }
}
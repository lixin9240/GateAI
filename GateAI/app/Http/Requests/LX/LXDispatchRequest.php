<?php

namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->route()?->getActionMethod();

        // 单孔开度下发
        if ($method === 'gateExecute') {
            return [
                'reservoir_id'  => 'required|integer|exists:reservoirs,id',
                'equipment_id'  => 'required|integer|exists:equipment,id',
                'target_opening' => 'required|numeric|min:0|max:100',
                'decision_id'   => 'nullable|integer|exists:dispatch_decisions,id',
                'operate_note'  => 'nullable|string|max:200',
            ];
        }

        // 批量孔开度下发
        if ($method === 'gateExecuteBatch') {
            return [
                'reservoir_id'          => 'required|integer|exists:reservoirs,id',
                'decision_id'           => 'nullable|integer|exists:dispatch_decisions,id',
                'gates'                 => 'required|array|min:1',
                'gates.*.equipment_id'  => 'required|integer|exists:equipment,id',
                'gates.*.target_opening' => 'required|numeric|min:0|max:100',
                'operate_note'          => 'nullable|string|max:200',
            ];
        }

        // 切换手动/自动模式
        if ($method === 'switchMode') {
            return [
                'reservoir_id' => 'required|integer|exists:reservoirs,id',
                'mode'         => 'required|string|in:manual,auto',
            ];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'gates.required'              => '请至少指定一个闸门',
            'gates.*.equipment_id.required' => '闸门设备ID不能为空',
            'gates.*.equipment_id.exists'   => '闸门设备不存在',
            'gates.*.target_opening.required' => '闸门目标开度不能为空',
            'gates.*.target_opening.max'      => '闸门开度不能超过100%',
            'gates.*.target_opening.min'      => '闸门开度不能小于0',
            'mode.in'                     => '模式只能为 manual 或 auto',
        ];
    }
}

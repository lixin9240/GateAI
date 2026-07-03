<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class EquipmentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function all($keys = null): array
    {
        return array_merge(parent::all(), $this->route()->parameters());
    }

    public function rules(): array
    {
        return [
            'id'     => 'required|integer|exists:equipment,id',
            'status' => 'required|string|in:online,offline,maintenance',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required'     => '未传必填参数id',
            'id.integer'      => 'id必须是整数',
            'id.exists'       => '设备不存在',
            'status.required' => '状态不能为空',
            'status.in'       => '状态值不合法：online / offline / maintenance',
        ];
    }
}

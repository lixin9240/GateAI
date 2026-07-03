<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class EquipmentShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 将路由参数 {id} 合并到请求中，使其可被 validate 捕获
     */
    public function all($keys = null): array
    {
        return array_merge(parent::all(), $this->route()->parameters());
    }

    public function rules(): array
    {
        return [
            'id' => 'required|integer|exists:equipment,id',
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => '未传必填参数id',
            'id.integer'  => 'id必须是整数',
            'id.exists'   => '设备不存在',
        ];
    }
}

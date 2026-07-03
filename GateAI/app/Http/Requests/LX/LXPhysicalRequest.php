<?php

namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXPhysicalRequest extends FormRequest
{
    public function rules(): array
    {
        // 新增物理参数规则
        if ($this->isMethod('post')) {
            return [
                'reservoir_id'  => 'required|exists:reservoirs,id',// 水库ID
                'water_level'   => 'required|numeric|min:0',// 水位
                'surface_area'  => 'required|numeric|min:0',// 库区面积
                'max_discharge' => 'numeric|min:0|nullable',// 最大流量
                'remark'        => 'string|max:255|nullable',// 备注
            ];
        }

        return [
            'reservoir_id' => 'required|exists:reservoirs,id',// 水库ID
            'page'         => 'integer|min:1',// 页码
            'page_size'    => 'integer|min:1|max:100',// 每页数量
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.required' => '水库ID不能为空',
            'water_level.required'  => '水位不能为空',
            'surface_area.required' => '库区面积不能为空',
        ];
    }
}

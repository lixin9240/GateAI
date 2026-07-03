<?php

namespace App\Http\Requests\Wjc;

use Illuminate\Foundation\Http\FormRequest;

class WjcReservoirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->method();
        
        // POST 新增
        if ($this->isMethod('POST')) {
            return [
                'name' => 'required|string|max:50|unique:reservoirs,name',
                'code' => 'required|string|unique:reservoirs,code',
                'type' => 'required|in:daily_regulation,seasonal,multi_year',
                'dead_water_level' => 'required|numeric|min:0',
                'normal_water_level' => 'required|numeric|gt:dead_water_level',
                'flood_limit_level' => 'required|numeric|gt:normal_water_level',
                'design_flood_level' => 'required|numeric|gt:flood_limit_level',
                'check_flood_level' => 'required|numeric|gt:design_flood_level',
                'total_capacity' => 'required|numeric|min:0',
                'installed_capacity' => 'nullable|numeric|min:0',
                'ecological_flow' => 'required|numeric|min:0',
            ];
        }

        // PUT 更新
        if ($method == 'PUT') {
            return [
                'name' => 'sometimes|string|max:50|unique:reservoirs,name,'.$this->route('id'),
                'code' => 'sometimes|string|unique:reservoirs,code,'.$this->route('id'),
                'type' => 'sometimes|in:daily_regulation,seasonal,multi_year',
                'dead_water_level' => 'sometimes|numeric|min:0',
                'normal_water_level' => 'sometimes|numeric|gt:dead_water_level',
                'ecological_flow' => 'sometimes|numeric|min:0',
                // 其他字段根据实际更新需求添加
            ];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'name.required' => '水库名称不能为空',
            'code.unique' => '水库编码已存在',
            'normal_water_level.gt' => '正常蓄水位必须大于死水位',
            'ecological_flow.required' => '生态流量是必填项',
        ];
    }
}
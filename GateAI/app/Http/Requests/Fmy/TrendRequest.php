<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class TrendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservoir_id' => 'required|integer|exists:reservoirs,id',
            'range'        => 'required|string|in:1h,6h,24h',
            'data_type'    => 'required|string|in:water_level,flow,power,gate_opening',
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.required' => '水库ID不能为空',
            'reservoir_id.exists'   => '水库不存在',
            'range.required'        => '时间区间不能为空',
            'range.in'              => '时间区间仅支持 1h/6h/24h',
            'data_type.required'    => '指标类型不能为空',
            'data_type.in'          => '指标类型不合法',
        ];
    }
}

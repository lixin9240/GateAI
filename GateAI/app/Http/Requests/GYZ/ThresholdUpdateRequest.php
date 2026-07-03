<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class ThresholdUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'warning_upper'    => 'nullable|numeric',
            'warning_lower'    => 'nullable|numeric',
            'critical_upper'   => 'nullable|numeric',
            'critical_lower'   => 'nullable|numeric',
            'debounce_seconds' => 'nullable|integer|min:1|max:300',
            'enabled'          => 'nullable|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'debounce_seconds.min' => '防抖时间最小1秒',
            'debounce_seconds.max' => '防抖时间最大300秒',
            'enabled.in'           => 'enabled只能为0或1',
        ];
    }
}

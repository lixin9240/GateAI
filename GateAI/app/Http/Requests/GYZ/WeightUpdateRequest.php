<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class WeightUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'enabled'        => 'nullable|integer|in:0,1',
            'power_weight'   => 'required|numeric|min:0|max:1',
            'safety_weight'  => 'required|numeric|min:0|max:1',
            'ecology_weight' => 'required|numeric|min:0|max:1',
            'preset_name'    => 'nullable|string|max:50',
            'description'    => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'power_weight.required'   => '发电权重不能为空',
            'safety_weight.required'  => '安全权重不能为空',
            'ecology_weight.required' => '生态权重不能为空',
        ];
    }
}

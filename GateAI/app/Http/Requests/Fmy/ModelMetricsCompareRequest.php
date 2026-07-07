<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class ModelMetricsCompareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservoir_id' => 'required|integer|exists:reservoirs,id',
            'model_a_id'   => 'required|integer|exists:settings_models,id|different:model_b_id',
            'model_b_id'   => 'required|integer|exists:settings_models,id',
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.required'   => '水库ID不能为空',
            'model_a_id.required'     => '模型A ID不能为空',
            'model_a_id.exists'       => '模型A不存在',
            'model_a_id.different'    => '两个模型不能相同',
            'model_b_id.required'     => '模型B ID不能为空',
            'model_b_id.exists'       => '模型B不存在',
        ];
    }
}

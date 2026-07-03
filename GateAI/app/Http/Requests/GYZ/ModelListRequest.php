<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class ModelListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page'      => 'nullable|integer|min:1',
            'page_size' => 'nullable|integer|min:1|max:100',
            'type'      => 'nullable|string|in:lstm_prediction,dqn_decision,fault_detection,general',
            'status'    => 'nullable|string|in:uploaded,validating,ready,active,deprecated',
            'keyword'   => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'page_size.max' => '每页最多100条',
            'type.in'       => '模型类型不合法',
            'status.in'     => '模型状态不合法',
        ];
    }
}

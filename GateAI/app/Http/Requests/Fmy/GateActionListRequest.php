<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class GateActionListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservoir_id' => 'nullable|integer|exists:reservoirs,id',
            'gate_id'      => 'nullable|integer|min:1',
            'page'         => 'nullable|integer|min:1',
            'page_size'    => 'nullable|integer|min:1|max:100',
            'start_time'   => 'nullable|date_format:Y-m-d H:i:s',
            'end_time'     => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:start_time',
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.exists'         => '水库不存在',
            'gate_id.min'                 => '闸门ID必须大于0',
            'end_time.after_or_equal'     => '结束时间不能早于开始时间',
        ];
    }
}

<?php

namespace App\Http\Requests\Wjc;

use Illuminate\Foundation\Http\FormRequest;

class PowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservoir_id' => 'nullable|integer|exists:reservoirs,id',
            'start_time'   => 'nullable|date',
            'end_time'     => 'nullable|date|after_or_equal:start_time',
            'granularity'  => 'nullable|in:hour,day',
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.exists'     => '关联的水库不存在',
            'end_time.after_or_equal' => '结束时间不能早于开始时间',
            'granularity.in'          => '粒度仅支持 hour 或 day',
        ];
    }
}

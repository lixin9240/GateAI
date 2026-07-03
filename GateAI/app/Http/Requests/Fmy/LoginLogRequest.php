<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class LoginLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page'       => 'nullable|integer|min:1',
            'page_size'  => 'nullable|integer|min:1|max:100',
            'start_time' => 'nullable|date_format:Y-m-d',
            'end_time'   => 'nullable|date_format:Y-m-d|after_or_equal:start_time',
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after_or_equal' => '结束时间不能早于开始时间',
        ];
    }
}

<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class RealtimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservoir_id' => 'required|integer|exists:reservoirs,id',
            'equipment_id' => 'nullable|integer|exists:equipment,id',
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.required' => '水库ID不能为空',
            'reservoir_id.exists'   => '水库不存在',
            'equipment_id.exists'   => '设备不存在',
        ];
    }
}

<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class GateListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservoir_id' => 'nullable|integer|exists:reservoirs,id',
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.exists' => '水库不存在',
        ];
    }
}

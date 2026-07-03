<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class EquipmentRestartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'force'  => 'nullable|boolean',
            'delay'  => 'nullable|integer|min:0|max:3600',
            'reason' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => '重启原因不能为空',
        ];
    }
}

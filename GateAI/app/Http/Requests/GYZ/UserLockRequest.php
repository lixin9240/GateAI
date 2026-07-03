<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class UserLockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason'       => 'required|string|max:255',
            'duration'     => 'nullable|integer|min:0',
            'force_logout' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => '锁定原因不能为空',
        ];
    }
}

<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class UserResetPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'new_password' => 'nullable|string|min:8|regex:/^(?=.*[a-zA-Z])(?=.*\d)/',
            'force_logout' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.min'   => '密码至少8位',
            'new_password.regex' => '密码必须包含字母和数字',
        ];
    }
}

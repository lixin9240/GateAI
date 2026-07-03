<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account'  => 'required|string|max:50',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'account.required'  => '登录账号不能为空',
            'password.required' => '密码不能为空',
        ];
    }
}

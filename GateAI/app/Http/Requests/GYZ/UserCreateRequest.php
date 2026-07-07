<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class UserCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'account'  => 'required|string|min:3|max:50',
            'password' => 'required|string|min:8',
            'realname' => 'required|string|max:30',
            'role_id'  => 'required|integer|exists:roles,id',
            'phone'    => 'nullable|string|max:11|regex:/^1[3-9]\d{9}$/',
            'email'    => 'nullable|email|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'account.required' => '登录账号不能为空',
            'account.min'      => '账号至少3位',
            'password.required' => '密码不能为空',
            'password.min'     => '密码至少8位',
            'realname.required' => '姓名不能为空',
            'role_id.required'  => '角色不能为空',
            'role_id.exists'    => '所选角色不存在',
        ];
    }
}

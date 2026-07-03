<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class UserCreateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'account'  => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'password' => 'required|string|min:8|regex:/^(?=.*[a-zA-Z])(?=.*\d)/',
            'realname' => 'required|string|max:30',
            'role_id'  => 'required|integer|exists:roles,id',
            'phone'    => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'account.required' => '登录账号不能为空',
            'account.regex'    => '账号只能包含字母、数字、下划线',
            'account.min'      => '账号至少3位',
            'password.required' => '密码不能为空',
            'password.min'     => '密码至少8位',
            'password.regex'   => '密码必须包含字母和数字',
            'realname.required' => '姓名不能为空',
            'role_id.required'  => '角色不能为空',
            'role_id.exists'    => '所选角色不存在',
        ];
    }
}

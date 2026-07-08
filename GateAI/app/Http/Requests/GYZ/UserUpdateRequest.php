<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'realname'   => 'nullable|string|max:30',
            'role_id'    => 'nullable|integer|exists:roles,id',
            'phone'      => 'nullable|string|max:11|regex:/^1[3-9]\d{9}$/',
            'email'      => 'nullable|email|max:100',
            'avatar'     => 'nullable|max:255',
            'is_enabled' => 'nullable|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.exists' => '所选角色不存在',
            'phone.regex'    => '手机号格式不正确',
            'phone.max'      => '手机号最多11位',
            'email.email'    => '邮箱格式不正确',
            'is_enabled.in'  => 'is_enabled只能为0或1',
        ];
    }
}

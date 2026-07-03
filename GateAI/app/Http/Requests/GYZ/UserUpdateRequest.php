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
            'phone'      => 'nullable|string|max:20',
            'is_enabled' => 'nullable|integer|in:0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.exists'   => '所选角色不存在',
            'is_enabled.in'    => 'is_enabled只能为0或1',
        ];
    }
}

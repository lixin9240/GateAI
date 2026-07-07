<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvatarUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'avatar' => 'required|file|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => '头像文件不能为空',
            'avatar.file'     => '头像必须是文件',
            'avatar.image'    => '头像必须是图片',
            'avatar.mimes'    => '头像格式不支持，支持：jpeg, png, jpg, gif, webp',
            'avatar.max'      => '头像大小不能超过2MB',
        ];
    }
}

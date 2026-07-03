<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class UserListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page'       => 'nullable|integer|min:1',
            'page_size'  => 'nullable|integer|min:1|max:100',
            'role_id'    => 'nullable|integer|min:1',
            'is_enabled' => 'nullable|integer|in:0,1',
            'keyword'    => 'nullable|string|max:50',
        ];
    }
}

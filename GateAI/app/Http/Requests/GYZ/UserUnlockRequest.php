<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class UserUnlockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:255',
        ];
    }
}

<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class ModelActivateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'force'               => 'nullable|boolean',
            'rollback_on_failure' => 'nullable|boolean',
        ];
    }
}

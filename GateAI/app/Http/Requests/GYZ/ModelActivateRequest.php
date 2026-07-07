<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class ModelActivateRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
        // force 和 rollback_on_failure 在 Controller 中有默认值，无需前端传参
    }
}

<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class ModelMetricsHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reservoir_id' => 'required|integer|exists:reservoirs,id',
            'days'         => 'nullable|integer|min:1|max:90',
        ];
    }
}

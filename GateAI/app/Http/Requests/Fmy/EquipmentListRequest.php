<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class EquipmentListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page'          => 'nullable|integer|min:1',
            'page_size'     => 'nullable|integer|min:1|max:100',
            'reservoir_id'  => 'nullable|integer|exists:reservoirs,id',
            'type'          => 'nullable|string|max:50',
            'status'        => 'nullable|string|in:active,offline,fault,maintenance',
            'keyword'       => 'nullable|string|max:100',
            'format'        => 'nullable|string|in:csv,xlsx',
        ];
    }
}

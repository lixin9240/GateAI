<?php

namespace App\Http\Requests\Wjc;

use Illuminate\Foundation\Http\FormRequest;

class WjcDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('POST') && $this->route()->getName() === 'dispatch.execute') {
            return [
                'reservoir_id' => 'required|integer|exists:reservoirs,id',
                'target_opening' => 'required|numeric|min:0|max:100',
                'operate_note' => 'nullable|string|max:200'
            ];
        }

        if ($this->isMethod('POST') && $this->route()->getName() === 'dispatch.emergency-stop') {
            return [
                'reservoir_id' => 'required|integer|exists:reservoirs,id',
                'stop_reason' => 'required|string|max:200'
            ];
        }

        return [];
    }
}
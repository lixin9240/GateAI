<?php

namespace App\Http\Requests\Wjc;

use Illuminate\Foundation\Http\FormRequest;

class WjcAlarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->method();
        
        // PUT 确认/处置
        if ($method == 'PUT') {
            $id = $this->route('id');
            return [
                'dispose_note' => 'nullable|string|min:10|max:500', // 3.3处置需要
            ];
        }

        return [];
    }
}
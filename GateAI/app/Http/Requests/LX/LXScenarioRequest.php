<?php

namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page'      => 'integer|min:1',
            'page_size' => 'integer|min:1|max:100',
            'type'      => 'in:production,energy,fault',
            'status'    => 'in:active,inactive,draft',
            'keyword'   => 'string|max:100',
        ];
    }
}

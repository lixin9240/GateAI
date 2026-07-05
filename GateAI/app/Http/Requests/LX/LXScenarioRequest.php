<?php
// 仿真场景请求
namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        $method = $this->route()->getActionMethod();

        if ($method === 'store') {
            return [
                'name'            => 'required|string|max:100',
                'type'            => 'required|in:production,energy,fault',
                'description'     => 'string|nullable',
                'status'          => 'in:active,inactive,draft|nullable',
                'model_id'        => 'exists:settings_models,id|nullable',
                'scenario_config' => 'array|nullable',
                'duration'        => 'integer|min:60',
                'speed'           => 'numeric|min:0.1|max:10.0',
            ];
        }

        if ($method === 'update') {
            return [
                'name'            => 'string|max:100',
                'type'            => 'in:production,energy,fault',
                'description'     => 'string|nullable',
                'status'          => 'in:active,inactive,draft',
                'model_id'        => 'exists:settings_models,id|nullable',
                'scenario_config' => 'array|nullable',
                'duration'        => 'integer|min:60',
                'speed'           => 'numeric|min:0.1|max:10.0',
            ];
        }

        return [
            'page'      => 'integer|min:1',
            'page_size' => 'integer|min:1|max:100',
            'type'      => 'in:production,energy,fault',
            'status'    => 'in:active,inactive,draft',
            'keyword'   => 'string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '场景名称不能为空',
            'type.required' => '场景类型不能为空',
        ];
    }
}

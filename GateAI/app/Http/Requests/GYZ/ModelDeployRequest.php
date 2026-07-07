<?php

namespace App\Http\Requests\Gyz;

use Illuminate\Foundation\Http\FormRequest;

class ModelDeployRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'edge_node_ids' => 'required|array|min:1',
            'edge_node_ids.*' => 'integer|min:1',
            'strategy'      => 'nullable|string|in:immediate,gradual,scheduled',
        ];
    }

    public function messages(): array
    {
        return [
            'edge_node_ids.required' => '目标边缘节点不能为空',
            'edge_node_ids.array'    => '边缘节点ID格式不正确',
            'edge_node_ids.min'      => '至少选择一个边缘节点',
            'edge_node_ids.*.exists' => '边缘节点 :input 不存在，请从边缘节点列表中选取合法ID',
        ];
    }
}

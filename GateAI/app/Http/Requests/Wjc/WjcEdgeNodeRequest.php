<?php

namespace App\Http\Requests\Wjc;

use Illuminate\Foundation\Http\FormRequest;

class WjcEdgeNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $method = $this->method();

        // 注册/新增
        if ($method == 'POST' && !str_contains($this->path(), 'heartbeat')) {
            return [
                'name' => 'required|string|max:50|unique:edge_nodes,name',
                'code' => 'required|string|unique:edge_nodes,code',
                'reservoir_id' => 'required|integer|exists:reservoirs,id',
                'ip' => 'nullable|ipv4',
                'location' => 'nullable|string|max:100',
            ];
        }

        // 心跳上报
        if ($method == 'POST' && str_contains($this->path(), 'heartbeat')) {
            return [
                'status' => 'nullable|in:online,offline,fault',
                'cpu_usage' => 'nullable|numeric|min:0|max:100',
                'memory_usage' => 'nullable|numeric|min:0|max:100',
                'model_version' => 'nullable|string|max:50',
                'autonomy_mode' => 'nullable|boolean',
            ];
        }

        return [];
    }

    public function messages(): array
    {
        return [
            'name.required' => '节点名称不能为空',
            'code.unique' => '节点编码已存在',
            'reservoir_id.exists' => '关联的水库不存在',
        ];
    }
}
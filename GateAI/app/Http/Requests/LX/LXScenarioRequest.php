<?php
// 仿真场景请求
namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        // 仿真场景规则
        return [
            'page'      => 'integer|min:1',// 页码
            'page_size' => 'integer|min:1|max:100',// 每页数量
            'type'      => 'in:production,energy,fault',// 类型
            'status'    => 'in:active,inactive,draft',// 状态
            'keyword'   => 'string|max:100',// 关键词
        ];
    }
}

<?php
// 历史查询请求
namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXHistoryRequest extends FormRequest
{
    public function rules(): array
    {
        // 12.5 边缘端历史查询
        if ($this->isMethod('post')) {
            return [
                'equipment_ids'   => 'required|array|min:1',// 设备ID列表
                'equipment_ids.*' => 'integer|exists:equipment,id',// 设备ID
                'start_time'      => 'required|date',// 开始时间
                'end_time'        => 'required|date|after_or_equal:start_time',// 结束时间
                'metrics'         => 'required|array|min:1',// 导出指标
                'format'          => 'in:csv,excel,json',// 导出格式
                'interval'       => 'string',// 时间间隔
                'file_name'      => 'string|max:100|nullable',// 文件名
                'email'          => 'email|nullable',// 邮箱
                'page'         => 'integer|min:1',// 页码
                'page_size'    => 'integer|min:1|max:10000',// 每页数量
            ];
        }

        return [
            'reservoir_id' => 'required|exists:reservoirs,id',// 水库ID
            'equipment_id' => 'exists:equipment,id|nullable',//设备id
            'start_time'   => 'required|date',// 开始时间
            'end_time'     => 'required|date|after_or_equal:start_time',// 结束时间
            'metrics'      => 'string|nullable',// 导出指标
            'interval'     => 'in:1m,5m,1h,1d|nullable',// 时间间隔
            'page'         => 'integer|min:1',// 页码
            'page_size'    => 'integer|min:1|max:10000',// 每页数量
        ];
    }

    public function messages(): array
    {
        return [
            'reservoir_id.required'    => '水库ID不能为空',
            'start_time.required'      => '开始时间不能为空',
            'end_time.required'        => '结束时间不能为空',
            'equipment_ids.required'   => '设备ID列表不能为空',
            'metrics.required'         => '导出指标不能为空',
        ];
    }
}

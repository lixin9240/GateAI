<?php

namespace App\Http\Requests\Fmy;

use Illuminate\Foundation\Http\FormRequest;

class ModelMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $route = $this->route()->getName();

        // 指标明细列表
        if ($route === 'fmy.metrics.list') {
            return [
                'reservoir_id' => 'integer|exists:reservoirs,id|nullable',
                'health_grade' => 'string|in:S,A,B,C,D|nullable',
                'start_time'   => 'date|nullable',
                'end_time'     => 'date|nullable',
                'page'         => 'integer|min:1|nullable',
                'page_size'    => 'integer|min:1|max:100|nullable',
            ];
        }

        // 最新指标 / 历史趋势
        return [
            'reservoir_id' => 'required|integer|exists:reservoirs,id',
        ];
    }
}

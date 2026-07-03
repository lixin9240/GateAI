<?php
// 边缘端数据上报请求
namespace App\Http\Requests\LX;

use Illuminate\Foundation\Http\FormRequest;

class LXEdgeRequest extends FormRequest
{
    public function rules(): array
    {
        $route = $this->route()->getName();
        // 12.1 边缘端上报监控数据
        if ($route === 'edge.monitoring') {
            return [
                'reservoir_id'               => 'required|exists:reservoirs,id',// 水库ID
                'edge_node_id'               => 'required|exists:edge_nodes,id',// 边节点ID
                'data'                       => 'required|array|max:1000',// 监控数据数组
                'data.*.timestamp'           => 'required|date',// 时间戳
                'data.*.upstream_level'      => 'required|numeric',// 上游水位
                'data.*.downstream_level'    => 'required|numeric',// 下游水位
                'data.*.water_head'          => 'required|numeric',// 水头
                'data.*.inflow_rate'         => 'required|numeric',// 入流率
                'data.*.outflow_rate'        => 'required|numeric',// 出流率
                'data.*.gate_opening'        => 'required|numeric|min:0|max:100',// 门开度
                'data.*.power_output'        => 'required|numeric',// 功率输出
                'data.*.cumulative_energy'   => 'numeric|nullable',// 累计能量
            ];
        }
        // 12.2 边缘端上报物理参数
        if ($route === 'edge.dispatch') {
            return [
                'trace_id'            => 'required|string',// 跟踪ID
                'reservoir_id'        => 'required|exists:reservoirs,id',// 水库ID
                'edge_node_id'        => 'required|exists:edge_nodes,id',// 边节点ID
                'prediction_id'       => 'required|integer',// 预测ID
                'decision_time'       => 'required|date',// 决策时间
                'decision_mode'       => 'required|in:L1,L2,L3',// 决策模式
                'risk_rank'           => 'required|integer|min:1|max:3',// 风险等级
                'upstream_level'      => 'required|numeric',// 上游水位
                'downstream_level'    => 'required|numeric',// 下游水位
                'inflow_rate'         => 'required|numeric',// 入流率
                'current_opening'     => 'required|numeric|min:0|max:100',// 当前门开度
                'lstm_predictions'    => 'required|array',// LSTM预测数组
                'recommended_opening' => 'required|numeric|min:0|max:100',// 推荐门开度
                'confidence'          => 'required|numeric|min:0|max:100',// 置信度
                'factors'             => 'required|array',// 因子数组
                'alternatives'        => 'required|array',// 选项数组
                'weights_used'        => 'required|array',// 权重数组
                'reward_score'        => 'numeric|nullable',// 奖励分数
                'physics_validation'  => 'array|nullable',// 物理验证数组
            ];
        }
        // 12.3 边缘端上报反馈
        if ($route === 'edge.feedback') {
            return [
                'status'           => 'required|in:executed,failed',// 执行状态
                'executed_at'      => 'required|date',// 执行时间
                'actual_opening'   => 'numeric|min:0|max:100|nullable',// 实际门开度
                'duration_ms'      => 'integer|nullable',// 执行时长（毫秒）
                'actuator_current' => 'numeric|nullable',// 执行器电流
                'is_smoothed'      => 'boolean|nullable',// 是否平滑
                'execution_result' => 'array|nullable',// 执行结果数组
            ];
        }

        // edge.alarm
        return [
            'reservoir_id'    => 'required|exists:reservoirs,id',// 水库ID
            'edge_node_id'    => 'required|exists:edge_nodes,id',// 边节点ID
            'equipment_id'    => 'required|exists:equipment,id',// 设备ID
            'type'            => 'required|in:water_level,flow,gate,power,equipment,physics_violation,comm_loss',// 告警类型
            'level'           => 'required|in:urgent,important,normal',// 告警级别
            'message'         => 'required|string|max:500',// 告警描述
            'threshold_id'    => 'exists:settings_thresholds,id|nullable',// 阈值ID
            'metric_value'    => 'required|numeric',// 指标值
            'threshold_value' => 'required|numeric',// 阈值
            'duration'        => 'required|integer|min:0',// 持续时间（秒）
            'exceed_start'    => 'required|date',// 超出开始时间
            'trace_id'        => 'string|nullable',// 跟踪ID
        ];
    }
    // 12.4 边缘端上报告警
    public function messages(): array
    {
        return [
            'data.required'           => '监测数据不能为空',
            'data.max'                => '单次最多上报1000条数据',
            'type.required'           => '告警类型不能为空',
            'level.required'          => '告警级别不能为空',
            'message.required'        => '告警描述不能为空',
            'status.required'         => '执行状态不能为空',
            'executed_at.required'    => '执行时间不能为空',
        ];
    }
}

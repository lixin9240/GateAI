<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchDecision extends Model
{
    protected $table = 'dispatch_decisions';

    protected $fillable = [
        'trace_id',            // 全链路追踪ID
        'reservoir_id',        // 所属水库
        'edge_node_id',        // 边缘节点ID
        'prediction_id',       // 关联LSTM预测ID
        'decision_time',       // 决策时间
        'decision_mode',       // L1 / L2 / L3
        'risk_rank',           // 风险等级 1~3
        'upstream_level',      // 上游水位（m）
        'downstream_level',    // 下游水位（m）
        'inflow_rate',         // 入库流量（m³/s）
        'current_opening',     // 当前闸门开度（%）
        'lstm_predictions',    // LSTM预测结果
        'recommended_opening', // 推荐闸门开度（%）
        'confidence',          // 置信度 0~100
        'factors',             // 影响因素列表
        'alternatives',        // 方案对比
        'weights_used',        // 使用的权重配置
        'reward_score',        // 奖励函数得分
        'physics_validation',  // 物理校验结果
        'execution_status',    // 执行状态
        'executed_opening',    // 实际执行开度（%）
        'executed_at',         // 执行时间
        'confirmed_by',        // 确认人
        'reject_reason',       // 拒绝原因
        'actual_level_after',  // 执行后实际水位（m）
        'actual_power_after',  // 执行后实际功率（kW）
    ];

    protected $casts = [
        'decision_time'      => 'datetime',
        'executed_at'        => 'datetime',
        'lstm_predictions'   => 'json',
        'factors'            => 'json',
        'alternatives'       => 'json',
        'weights_used'       => 'json',
        'physics_validation' => 'json',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class);
    }

    public function edgeNode()
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }
}

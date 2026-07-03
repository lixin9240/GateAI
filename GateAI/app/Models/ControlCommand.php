<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class ControlCommand extends Model
{
    use HasBeijingTime;

    protected $table = 'control_commands';

    protected $fillable = [
        'command_id',        // 全局唯一指令ID
        'trace_id',          // 全链路追踪ID
        'decision_id',       // 关联决策ID
        'gate_action_id',    // 关联闸门动作ID
        'edge_node_id',      // 目标边缘节点
        'operator_id',       // 操作人
        'command_type',      // 指令类型
        'payload',           // 指令负载
        'target_equipment',  // 目标设备
        'target_opening',    // 目标开度（%）
        'sign',              // 签名
        'nonce',             // 随机数
        'expire_at',         // 过期时间
        'status',            // 执行状态
        'sent_at',           // 下发时间
        'acknowledged_at',   // 边缘确认时间
        'verified_at',       // 校验通过时间
        'executed_at',       // 执行时间
        'feedback_at',       // 回执时间
        'full_delay_ms',     // 全链路耗时（ms）
        'execution_result',  // 执行回执详情
        'reject_reason',     // 拒绝原因
        'is_emergency',      // 是否急停指令
    ];

    protected $casts = [
        'payload'           => 'json',
        'execution_result'  => 'json',
        'sent_at'           => 'datetime',
        'acknowledged_at'   => 'datetime',
        'verified_at'       => 'datetime',
        'executed_at'       => 'datetime',
        'feedback_at'       => 'datetime',
        'expire_at'         => 'datetime',
    ];
}

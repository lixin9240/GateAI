<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GateAction extends Model
{
    protected $table = 'gate_actions';

    public $timestamps = false;

    protected $fillable = [
        'equipment_id',     // 闸门设备ID
        'decision_id',      // 关联决策ID
        'command_id',       // 关联指令ID
        'previous_opening', // 动作前开度（%）
        'target_opening',   // 目标开度（%）
        'actual_opening',   // 实际到位开度（%）
        'action_type',      // 动作类型
        'action_source',    // 动作来源
        'duration_ms',      // 动作耗时（ms）
        'actuator_current', // 推杆电流（A）
        'is_smoothed',      // 是否被平滑化修改
        'smooth_reason',    // 平滑原因
        'acted_at',         // 动作执行时间
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];
}

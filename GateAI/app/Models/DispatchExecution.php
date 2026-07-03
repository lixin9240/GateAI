<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class DispatchExecution extends Model
{
    use HasBeijingTime;

    protected $table = 'dispatch_executions';

    protected $fillable = [
        'plan_id',      // 调度计划ID
        'equipment_id', // 设备ID
        'action_type',  // 动作类型
        'target_value', // 目标值
        'executed_at',  // 执行时间
        'operator_id',  // 操作人
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];
}

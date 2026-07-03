<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class DispatchPlan extends Model
{
    use HasBeijingTime;

    protected $table = 'dispatch_plans';

    protected $fillable = [
        'reservoir_id', // 水库ID
        'plan_type',    // 计划类型
        'target_flow',  // 目标流量
        'start_time',   // 开始时间
        'end_time',     // 结束时间
        'status',       // 状态
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];
}

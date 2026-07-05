<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class Alarm extends Model
{
    use HasBeijingTime;

    protected $table = 'alarms';

    protected $fillable = [
        'alarm_no', // 告警编号
        'reservoir_id', // 水库ID
        'equipment_id', // 设备ID
        'type', // 告警类型
        'level', // 告警等级
        'message', // 告警消息
        'threshold_id', // 阈值ID
        'metric_value', // 指标值
        'threshold_value', // 阈值值
        'duration', // 持续时间
        'exceed_start', // 超出开始时间
        'status', // 状态
        'acknowledged_by', // 确认人
        'acknowledged_at', // 确认时间
        'disposed_by', // 处理人
        'disposed_at', // 处理时间
        'dispose_note', // 处理备注
        'resolved_by', // 解决人
        'resolved_at', // 解决时间
        'trace_id', // 跟踪ID
        'edge_node_id',// 边节点ID
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'disposed_at'     => 'datetime',
        'resolved_at'     => 'datetime',
        'exceed_start'    => 'datetime',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}

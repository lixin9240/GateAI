<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlarmExceedLog extends Model
{
    protected $table = 'alarm_exceed_logs';

    protected $fillable = [
        'equipment_id',      // 关联设备
        'metric',            // 监控指标
        'metric_value',      // 触发时实际值
        'threshold_value',   // 触发阈值
        'threshold_type',    // warning / critical
        'direction',         // upper / lower
        'exceed_start',      // 开始超限时间
        'exceed_end',        // 结束超限时间
        'duration',          // 持续时长（秒）
        'is_promoted',       // 是否已升级为正式告警
        'promoted_alarm_id', // 关联正式告警ID
        'edge_node_id',      // 来源边缘节点
    ];

    protected $casts = [
        'exceed_start' => 'datetime',
        'exceed_end'   => 'datetime',
    ];
}

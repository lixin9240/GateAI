<?php
// 监控数据模型
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringData extends Model
{
    protected $table = 'monitoring_data';

    protected $fillable = [
        'timestamp', // 时间戳
        'reservoir_id', // 水库ID
        'upstream_level', // 上游水位
        'downstream_level', // 下游水位
        'water_head', // 水头
        'inflow_rate', // 入流率
        'outflow_rate', // 出流率
        'gate_opening', // 门开度
        'power_output', // 功率输出
        'cumulative_energy', // 累计能量
        'edge_node_id', // 边节点ID
        'data_source', // 数据来源
        'is_anomaly', // 是否异常
    ];

    protected $casts = [
        'timestamp'     => 'datetime',
        'is_anomaly'    => 'boolean',
    ];
}

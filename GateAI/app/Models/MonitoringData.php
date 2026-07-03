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
        'timestamp',
        'reservoir_id',
        'upstream_level',
        'downstream_level',
        'water_head',
        'inflow_rate',
        'outflow_rate',
        'gate_opening',
        'power_output',
        'cumulative_energy',
        'edge_node_id',
        'data_source',
        'is_anomaly',
    ];

    protected $casts = [
        'timestamp'          => 'datetime',
        'upstream_level'     => 'decimal:3',
        'downstream_level'   => 'decimal:3',
        'water_head'         => 'decimal:3',
        'inflow_rate'        => 'decimal:3',
        'outflow_rate'       => 'decimal:3',
        'gate_opening'       => 'decimal:2',
        'power_output'       => 'decimal:3',
        'cumulative_energy'  => 'decimal:3',
        'is_anomaly'         => 'boolean',
    ];

    public const UPDATED_AT = null;

    /**
     * 所属水库
     */
    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    /**
     * 来源边缘节点
     */
    public function edgeNode()
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

    /**
     * 根据 data_type 返回对应的数值字段
     */
    public static function columnByDataType(string $dataType): string
    {
        return match ($dataType) {
            'water_level'  => 'water_head',
            'flow'         => 'inflow_rate',
            'power'        => 'power_output',
            'gate_opening' => 'gate_opening',
            default        => 'water_head',
        };
    }
}

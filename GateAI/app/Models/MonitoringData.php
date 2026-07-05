<?php

namespace App\Models;

use App\Models\Concerns\BeijingTime;
use Illuminate\Database\Eloquent\Model;

class MonitoringData extends Model
{
    use BeijingTime;

    protected $table = 'monitoring_data';

    public const UPDATED_AT = null;

    protected $fillable = [
        'timestamp',         // 数据时间戳
        'reservoir_id',      // 所属水库
        'upstream_level',    // 上游水位（m）
        'downstream_level',  // 下游水位（m）
        'water_head',        // 水头（m）
        'inflow_rate',       // 入库流量（m³/s）
        'outflow_rate',      // 出库流量（m³/s）
        'gate_opening',      // 闸门开度（%）
        'power_output',      // 发电功率（kW）
        'cumulative_energy', // 累计发电量（kWh）
        'edge_node_id',      // 来源边缘节点
        'data_source',       // 数据来源
        'is_anomaly',        // 是否异常值
    ];

    protected $casts = [
        'timestamp'         => 'datetime',
        'upstream_level'    => 'decimal:3',
        'downstream_level'  => 'decimal:3',
        'water_head'        => 'decimal:3',
        'inflow_rate'       => 'decimal:3',
        'outflow_rate'      => 'decimal:3',
        'gate_opening'      => 'decimal:2',
        'power_output'      => 'decimal:3',
        'cumulative_energy' => 'decimal:3',
        'is_anomaly'        => 'boolean',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    public function edgeNode()
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

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

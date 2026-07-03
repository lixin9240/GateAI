<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipment';

    public $timestamps = false;

    protected $fillable = [
        'name',         // 设备名称
        'code',         // 设备编码
        'type',         // 设备类型
        'reservoir_id', // 所属水库
        'status',       // 设备状态
        'location',     // 设备位置
        'manufacturer', // 设备制造商
        'model',        // 设备型号
        'serial_number',    // 设备序列号
        'purchase_date',    // 购买日期
        'warranty_expire',  // 保修到期日期
        'specs',            // 设备规格
        'current_metrics',  // 当前指标
        'health_score',     // 健康分数
        'tags',             // 标签
        'edge_node_id',     // 边缘节点 ID
        'plc_register',     // PLC 注册
        'communication_protocol', // 通信协议
        'heartbeat_interval',     // 心跳间隔
        'offline_threshold',      // 离线阈值
        'firmware_version',       // 固件版本
        'maintenance_count',      // 维护次数
        'last_maintenance_at',    // 最后维护时间
        'next_maintenance_at',    // 下次维护时间
        'total_runtime',  // 总运行时间
        'ip_address',     // IP 地址
        'port',           // 端口
        'last_online',    // 最后在线时间
        'created_by',     // 创建人
        'updated_by',     // 更新人
    ];

    protected $casts = [
        'specs'             => 'json',
        'current_metrics'   => 'json',
        'tags'              => 'json',
        'health_score'      => 'float',
        'purchase_date'     => 'date',
        'warranty_expire'   => 'date',
        'last_maintenance_at' => 'datetime',
        'next_maintenance_at' => 'datetime',
        'last_online'       => 'datetime',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class);
    }

    public function edgeNode()
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

    public function alarms()
    {
        return $this->hasMany(Alarm::class, 'equipment_id');
    }
}

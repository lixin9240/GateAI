<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasBeijingTime;

    use SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = [
        'name',                  // 设备名称
        'code',                  // 设备编号
        'type',                  // 设备类型
        'reservoir_id',          // 所属水库
        'status',                // 设备状态
        'location',              // 安装位置
        'manufacturer',          // 制造商
        'model',                 // 型号
        'serial_number',         // 序列号
        'purchase_date',         // 采购日期
        'warranty_expire',       // 质保到期日
        'specs',                 // 技术规格
        'current_metrics',       // 当前实时指标
        'health_score',          // 健康评分
        'tags',                  // 标签列表
        'edge_node_id',          // 所属边缘节点
        'plc_register',          // PLC寄存器地址
        'communication_protocol',// 通信协议
        'heartbeat_interval',    // 心跳间隔（秒）
        'offline_threshold',     // 离线判定阈值（秒）
        'firmware_version',      // 固件版本
        'maintenance_count',     // 维护次数
        'last_maintenance_at',   // 上次维护时间
        'next_maintenance_at',   // 下次计划维护时间
        'total_runtime',         // 累计运行时长（秒）
        'ip_address',            // IP地址
        'port',                  // 通信端口
        'last_online',           // 最后在线时间
        'created_by',            // 创建人
        'updated_by',            // 更新人
    ];

    protected $casts = [
        'specs'               => 'json',
        'current_metrics'     => 'json',
        'tags'                => 'json',
        'health_score'        => 'float',
        'purchase_date'       => 'date',
        'warranty_expire'     => 'date',
        'last_online'         => 'datetime',
        'last_maintenance_at' => 'datetime',
        'next_maintenance_at' => 'datetime',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    public function edgeNode()
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

    public function isEdgeGateway(): bool
    {
        return $this->type === 'edge_gateway';
    }

    public function alarms()
    {
        return $this->hasMany(Alarm::class, 'equipment_id');
    }
}

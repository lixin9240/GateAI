<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EdgeNode extends Model
{
    use HasBeijingTime;

    protected $table = 'edge_nodes';

    protected $fillable = [
        'name',                  // 节点名称
        'code',                  // 节点编号
        'reservoir_id',          // 所属水库
        'status',                // online / offline / fault
        'location',              // 安装位置
        'ip',                    // IP地址
        'last_heartbeat',        // 最后心跳时间
        'edge_version',          // 边缘端版本
        'model_version',         // AI模型版本
        'threshold_version',     // 阈值版本
        'weight_version',        // 权重版本
        'physics_config_version',// 物理配置版本
        'autonomy_mode',         // 是否断网自治
        'autonomy_since',        // 自治开始时间
        'cache_size',            // 本地缓存数据条数
        'cpu_usage',             // CPU使用率（%）
        'memory_usage',          // 内存使用率（%）
        'disk_usage',            // 磁盘使用率（%）
        'plc_status',            // PLC连接状态
        'plc_last_comm',         // PLC最后通信时间
        'total_uptime',          // 累计运行时长
    ];

    protected $casts = [
        'last_heartbeat' => 'datetime',
        'autonomy_since' => 'datetime',
        'plc_last_comm'  => 'datetime',
        'autonomy_mode'  => 'boolean',
        'cpu_usage'      => 'decimal:2',
        'memory_usage'   => 'decimal:2',
        'disk_usage'     => 'decimal:2',
        'total_uptime'   => 'integer',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'edge_node_id');
    }
}

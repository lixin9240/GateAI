<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class EdgeNode extends Model
{
    protected $table = 'edge_nodes';

    protected $fillable = [
        'name', 'code', 'reservoir_id', 'location',
        'ip', 'status', 'cpu_usage', 'memory_usage',
        'model_version', 'autonomy_mode', 'last_heartbeat'
        'name',
        'code',
        'reservoir_id',
        'status',
        'location',
        'ip',
        'last_heartbeat',
        'edge_version',
        'model_version',
        'threshold_version',
        'weight_version',
        'physics_config_version',
        'autonomy_mode',
        'autonomy_since',
        'cache_size',
        'cpu_usage',
        'memory_usage',
        'disk_usage',
        'plc_status',
        'plc_last_comm',
        'total_uptime',
    ];

    protected $casts = [
        'autonomy_mode' => 'boolean',
        'cpu_usage' => 'float',
        'memory_usage' => 'float',
        'last_heartbeat' => 'datetime',
        'last_heartbeat'        => 'datetime',
        'autonomy_since'        => 'datetime',
        'plc_last_comm'         => 'datetime',
        'autonomy_mode'         => 'boolean',
        'cpu_usage'             => 'decimal:2',
        'memory_usage'          => 'decimal:2',
        'disk_usage'            => 'decimal:2',
        'total_uptime'          => 'integer',
    ];

    // 关联水库
    /**
     * 所属水库
     */
    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class);
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    // 关联设备
    /**
     * 关联设备
     */
    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'edge_node_id');
    }
}


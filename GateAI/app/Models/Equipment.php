<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use SoftDeletes;

    protected $table = 'equipment';

    protected $fillable = [
        'name',
        'code',
        'type',
        'reservoir_id',
        'status',
        'location',
        'manufacturer',
        'model',
        'serial_number',
        'purchase_date',
        'warranty_expire',
        'specs',
        'current_metrics',
        'health_score',
        'tags',
        'edge_node_id',
        'plc_register',
        'communication_protocol',
        'heartbeat_interval',
        'offline_threshold',
        'firmware_version',
        'maintenance_count',
        'last_maintenance_at',
        'next_maintenance_at',
        'total_runtime',
        'ip_address',
        'port',
        'last_online',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'specs'              => 'array',
        'current_metrics'    => 'array',
        'tags'               => 'array',
        'health_score'       => 'decimal:2',
        'purchase_date'      => 'date',
        'warranty_expire'    => 'date',
        'last_online'        => 'datetime',
        'last_maintenance_at' => 'datetime',
        'next_maintenance_at' => 'datetime',
    ];

    /**
     * 所属水库
     */
    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    /**
     * 所属边缘节点
     */
    public function edgeNode()
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

    /**
     * 是否为网关设备
     */
    public function isEdgeGateway(): bool
    {
        return $this->type === 'edge_gateway';
    }
}

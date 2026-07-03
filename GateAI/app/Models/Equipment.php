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
        'name', 'code', 'type', 'reservoir_id', 'status',
        'location', 'manufacturer', 'model', 'serial_number',
        'purchase_date', 'warranty_expire', 'specs', 'current_metrics',
        'health_score', 'tags', 'edge_node_id', 'plc_register',
        'communication_protocol', 'heartbeat_interval', 'offline_threshold',
        'firmware_version', 'maintenance_count', 'last_maintenance_at',
        'next_maintenance_at', 'total_runtime', 'ip_address', 'port',
        'last_online', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'specs'             => 'json',
        'current_metrics'   => 'json',
        'tags'              => 'json',
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

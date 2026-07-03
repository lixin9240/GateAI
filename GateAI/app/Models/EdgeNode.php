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
    ];

    protected $casts = [
        'autonomy_mode' => 'boolean',
        'cpu_usage' => 'float',
        'memory_usage' => 'float',
        'last_heartbeat' => 'datetime',
    ];

    // 关联水库
    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class);
    }

    // 关联设备
    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'edge_node_id');
    }
}


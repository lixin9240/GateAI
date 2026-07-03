<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EdgeNode extends Model
{
    use SoftDeletes;

    protected $table = 'edge_nodes';

    protected $fillable = [
        'name', 'code', 'reservoir_id', 'location', 'ip',
        'status', 'last_heartbeat', 'cpu_usage', 'memory_usage',
        'disk_usage', 'plc_status', 'autonomy_mode', 'cache_size',
        'model_version', 'threshold_version', 'weight_version',
        'physics_config_version',
    ];

    protected $casts = [
        'id'              => 'integer',
        'reservoir_id'    => 'integer',
        'cpu_usage'       => 'decimal:2',
        'memory_usage'    => 'decimal:2',
        'disk_usage'      => 'decimal:2',
        'autonomy_mode'   => 'integer',
        'cache_size'      => 'integer',
        'last_heartbeat'  => 'datetime',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alarm extends Model
{
    protected $table = 'alarms';

    protected $fillable = [
        'alarm_no',
        'reservoir_id',
        'equipment_id',
        'type',
        'level',
        'message',
        'threshold_id',
        'metric_value',
        'threshold_value',
        'duration',
        'exceed_start',
        'status',
        'acknowledged_at',
        'acknowledged_by',
        'disposed_at',
        'disposed_by',
        'dispose_note',
        'resolved_at',
        'resolved_by',
        'trace_id',
        'edge_node_id',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'disposed_at'      => 'datetime',
        'resolved_at'      => 'datetime',
        'exceed_start'     => 'datetime',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class);
    }
}

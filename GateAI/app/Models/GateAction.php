<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GateAction extends Model
{
    protected $table = 'gate_actions';

    public $timestamps = false;

    protected $fillable = [
        'equipment_id',
        'decision_id',
        'command_id',
        'previous_opening',
        'target_opening',
        'actual_opening',
        'action_type',
        'action_source',
        'duration_ms',
        'actuator_current',
        'is_smoothed',
        'smooth_reason',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];
}

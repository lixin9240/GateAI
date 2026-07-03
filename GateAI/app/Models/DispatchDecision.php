<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchDecision extends Model
{
    protected $table = 'dispatch_decisions';

    protected $fillable = [
        'trace_id',
        'reservoir_id',
        'edge_node_id',
        'prediction_id',
        'decision_time',
        'decision_mode',
        'risk_rank',
        'upstream_level',
        'downstream_level',
        'inflow_rate',
        'current_opening',
        'lstm_predictions',
        'recommended_opening',
        'confidence',
        'factors',
        'alternatives',
        'weights_used',
        'reward_score',
        'physics_validation',
        'execution_status',
        'executed_opening',
        'executed_at',
        'confirmed_by',
        'reject_reason',
        'actual_level_after',
        'actual_power_after',
    ];

    protected $casts = [
        'decision_time'      => 'datetime',
        'executed_at'        => 'datetime',
        'lstm_predictions'   => 'json',
        'factors'            => 'json',
        'alternatives'       => 'json',
        'weights_used'       => 'json',
        'physics_validation' => 'json',
    ];
}

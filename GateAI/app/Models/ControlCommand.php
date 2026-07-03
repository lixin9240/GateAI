<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ControlCommand extends Model
{
    protected $table = 'control_commands';

    protected $fillable = [
        'command_id',
        'trace_id',
        'decision_id',
        'gate_action_id',
        'edge_node_id',
        'operator_id',
        'command_type',
        'payload',
        'target_equipment',
        'target_opening',
        'sign',
        'nonce',
        'expire_at',
        'status',
        'sent_at',
        'acknowledged_at',
        'verified_at',
        'executed_at',
        'feedback_at',
        'full_delay_ms',
        'execution_result',
        'reject_reason',
        'is_emergency',
    ];

    protected $casts = [
        'payload'           => 'json',
        'execution_result'  => 'json',
        'sent_at'           => 'datetime',
        'acknowledged_at'   => 'datetime',
        'verified_at'       => 'datetime',
        'executed_at'       => 'datetime',
        'feedback_at'       => 'datetime',
        'expire_at'         => 'datetime',
    ];
}

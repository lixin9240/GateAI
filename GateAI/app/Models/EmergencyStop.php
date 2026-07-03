<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyStop extends Model
{
    protected $table = 'emergency_stops';

    protected $fillable = [
        'trigger_user_id',
        'decision_id',
        'command_id',
        'stop_reason',
        'trigger_time',
        'edge_ack_time',
        'plc_shut_time',
        'recover_user_id',
        'recover_time',
    ];

    protected $casts = [
        'trigger_time'   => 'datetime',
        'edge_ack_time'  => 'datetime',
        'plc_shut_time'  => 'datetime',
        'recover_time'   => 'datetime',
    ];
}

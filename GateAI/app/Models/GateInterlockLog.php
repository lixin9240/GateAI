<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class GateInterlockLog extends Model
{
    use HasBeijingTime;

    protected $table = 'gate_interlock_logs';

    public $timestamps = false;

    protected $fillable = [
        'reservoir_id',
        'rule_id',
        'decision_id',
        'trigger_time',
        'gate1_opening_before',
        'gate2_opening_before',
        'gate3_opening_before',
        'upstream_level',
        'downstream_level',
        'inflow_rate',
        'gate1_opening_after',
        'gate2_opening_after',
        'gate3_opening_after',
        'action_detail',
    ];

    protected $casts = [
        'trigger_time'  => 'datetime',
        'action_detail' => 'json',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class);
    }

    public function rule()
    {
        return $this->belongsTo(GateInterlockRule::class, 'rule_id');
    }

    public function decision()
    {
        return $this->belongsTo(DispatchDecision::class, 'decision_id');
    }
}

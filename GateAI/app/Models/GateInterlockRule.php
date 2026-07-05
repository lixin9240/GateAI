<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class GateInterlockRule extends Model
{
    use HasBeijingTime;

    protected $table = 'gate_interlock_rules';

    protected $fillable = [
        'reservoir_id',
        'rule_code',
        'rule_name',
        'description',
        'enabled',
        'priority',
        'trigger_conditions',
        'constraint_action',
    ];

    protected $casts = [
        'enabled'           => 'boolean',
        'priority'          => 'integer',
        'trigger_conditions' => 'json',
        'constraint_action'  => 'json',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class);
    }

    public function logs()
    {
        return $this->hasMany(GateInterlockLog::class, 'rule_id');
    }
}

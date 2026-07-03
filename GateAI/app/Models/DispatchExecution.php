<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchExecution extends Model
{
    protected $table = 'dispatch_executions';

    protected $fillable = [
        'plan_id',
        'equipment_id',
        'action_type',
        'target_value',
        'executed_at',
        'operator_id',
    ];

    protected $casts = [
        'executed_at' => 'datetime',
    ];
}

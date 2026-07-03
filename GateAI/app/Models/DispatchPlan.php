<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchPlan extends Model
{
    protected $table = 'dispatch_plans';

    protected $fillable = [
        'reservoir_id',
        'plan_type',
        'target_flow',
        'start_time',
        'end_time',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];
}

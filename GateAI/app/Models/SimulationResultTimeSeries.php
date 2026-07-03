<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationResultTimeSeries extends Model
{
    protected $table = 'simulation_result_time_series';

    protected $fillable = [
        'result_id', // 仿真结果ID
        'timestamp', // 时间点
        'values',    // 各指标数值
    ];

    protected $casts = [
        'values'    => 'json',
        'timestamp' => 'datetime',
    ];
}

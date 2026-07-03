<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationResult extends Model
{
    protected $table = 'simulation_results';

    protected $fillable = [
        'simulation_id', // 仿真任务ID
        'scenario_id',   // 场景ID
        'status',        // 最终状态
        'report_id',     // 关联报告ID
        'report_status', // 报告状态
        'start_time',    // 开始时间
        'end_time',      // 结束时间
        'summary',       // 汇总统计
    ];

    protected $casts = [
        'summary'    => 'json',
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    public function timeSeries()
    {
        return $this->hasMany(SimulationResultTimeSeries::class, 'result_id');
    }
}

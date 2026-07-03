<?php
// 模拟结果模型
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationResult extends Model
{
    protected $table = 'simulation_results';

    protected $fillable = [
        'simulation_id',// 关联模拟 ID
        'scenario_id', // 关联场景 ID
        'status', // 状态
        'report_id', // 报告 ID
        'report_status', // 报告状态
        'start_time', // 开始时间
        'end_time', // 结束时间
        'summary', // 摘要
        'created_by', // 创建人
        'updated_by', // 更新人
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

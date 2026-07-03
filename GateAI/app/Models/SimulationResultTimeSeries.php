<?php
// 模拟结果时间序列模型
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationResultTimeSeries extends Model
{
    protected $table = 'simulation_result_time_series';

    public $timestamps = false;

    protected $fillable = [
        'result_id', // 关联模拟结果 ID
        'timestamp', // 时间戳
        'values', // 值
    ];

    protected $casts = [
        'values'    => 'json',
        'timestamp' => 'datetime',
    ];
}

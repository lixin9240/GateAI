<?php
// 模拟任务模型
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationTask extends Model
{
    protected $table = 'simulation_tasks';

    protected $fillable = [
        'task_no', // 任务编号
        'scenario_id', // 关联场景 ID
        'model_id', // 关联模型 ID
        'duration', // 持续时间
        'speed', // 速度
        'params', // 参数
        'status', // 状态
        'start_time', // 开始时间
        'end_time', // 结束时间
        'estimated_end_time', // 预计结束时间
        'ws_endpoint', // WebSocket 端点
        'progress', // 进度
        'result_summary', // 结果摘要
        'anomaly_count', // 异常数量
        'max_upstream_level', // 最大上游水位
        'min_upstream_level', // 最小上游水位
        'max_downstream_level', // 最大下游水位
        'max_inflow_rate', // 最大入流率
        'max_outflow_rate', // 最大出流率
        'total_energy', // 总能量
        'total_discharge', // 总流量
        'error_msg', // 错误信息
        'created_by', // 创建人
        'updated_by', // 更新人
    ];

    protected $casts = [
        'params'           => 'json',
        'result_summary'   => 'json',
        'duration'         => 'integer',
        'speed'            => 'float',
        'progress'         => 'float',
        'start_time'       => 'datetime',
        'end_time'         => 'datetime',
        'estimated_end_time' => 'datetime',
    ];

    public function scenario()
    {
        return $this->belongsTo(SimulationScenario::class, 'scenario_id');
    }
}

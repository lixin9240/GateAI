<?php
// 模拟事件模型
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationIncident extends Model
{
    protected $table = 'simulation_incidents';

    protected $fillable = [
        'incident_name',// 事件名称
        'description', // 事件描述
        'severity', // 事件严重性
        'equipment_id', // 关联设备 ID
        'occurred_at', // 事件发生时间
        'resolved_at', // 事件解决时间
        'duration', // 事件持续时间
        'root_cause', // 事件根因
        'simulation_id', // 关联模拟 ID
        'raw_data', // 原始数据
        'scenario_config', // 场景配置
        'incident_type', // 事件类型
        'resolution', // 解事件解决
        'responsibility', // 责任
        'lesson_learned', // 教训
        'related_alarms', // 相关告警
        'replayed_scenario_id', // 重放场景 ID
        'import_id', // 导入 ID
        'status', // 事件状态
        'created_by', // 创建人
        'updated_by', // 更新人
    ];

    protected $casts = [
        'raw_data'         => 'json',
        'scenario_config'  => 'json',
        'related_alarms'   => 'json',
        'occurred_at'      => 'datetime',
        'resolved_at'      => 'datetime',
        'duration'         => 'integer',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }
}

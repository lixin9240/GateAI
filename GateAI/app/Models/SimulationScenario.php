<?php
// 模拟场景模型
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SimulationScenario extends Model
{
    protected $table = 'simulation_scenarios';

    protected $fillable = [
        'name', // 名称
        'type', // 类型
        'description', // 描述
        'status', // 状态
        'model_id', // 关联模型 ID
        'scenario_config', // 场景配置
        'duration', // 持续时间
        'speed', // 速度
        'is_preset', // 是否预设
        'usage_count', // 使用次数
        'created_by', // 创建人
        'updated_by', // 更新人
    ];

    protected $casts = [
        'scenario_config' => 'json',
        'duration'        => 'integer',
        'speed'           => 'float',
        'is_preset'       => 'boolean',
        'usage_count'     => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

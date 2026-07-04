<?php
// 物理防护配置模型
namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class PhysicsGuardConfig extends Model
{
    use HasBeijingTime;

    protected $table = 'physics_guard_configs';

    protected $fillable = [
        'reservoir_id',// 所属水库
        'config_version',// 配置版本
        'is_active',// 是否启用
        'upstream_danger',// 上游危险值
        'upstream_emergency',// 上游紧急值
        'upstream_warning',// 上游警告值
        'upstream_min',// 上游最小值
        'ideal_min',// 理想最小值
        'ideal_max',// 理想最大值
        'downstream_danger',// 下游危险值
        'downstream_max',// 下游最大值
        'downstream_min',// 下游最小值
        'eco_flow_min',// 环境流最小值
        'reservoir_area',// 水库面积
        'max_level_change_per_hour',// 最大水位变化率（m/h）
        'shadow_lookahead_steps',// 阴影前向步数
        'shadow_danger_offset',// 阴影危险值偏移量（m）
        'deadband_percent',// 死区百分比（%）
        'max_rate_per_hour',// 最大流量（m³/h）
        'fusion_l3_confidence',// 3级融合置信度（%）
        'fusion_l3_risk',// 3级融合风险（%）
        'fusion_l2_confidence',// 2级融合置信度（%）
        'fusion_l2_risk',// 2级融合风险（%）
        'gate_max_discharge',// 门最大流量（m³/h）
        'description',// 描述
        'updated_by',// 更新人
    ];

    protected $casts = [
        'is_active'                => 'integer',
        'upstream_danger'          => 'decimal:2',
        'upstream_emergency'       => 'decimal:2',
        'upstream_warning'         => 'decimal:2',
        'upstream_min'             => 'decimal:2',
        'ideal_min'                => 'decimal:2',
        'ideal_max'                => 'decimal:2',
        'downstream_danger'        => 'decimal:2',
        'downstream_max'           => 'decimal:2',
        'downstream_min'           => 'decimal:2',
        'eco_flow_min'             => 'decimal:2',
        'reservoir_area'           => 'integer',
        'max_level_change_per_hour' => 'decimal:2',
        'shadow_lookahead_steps'   => 'integer',
        'shadow_danger_offset'     => 'decimal:2',
        'deadband_percent'         => 'decimal:4',
        'max_rate_per_hour'        => 'decimal:4',
        'fusion_l3_confidence'     => 'decimal:4',
        'fusion_l3_risk'           => 'decimal:4',
        'fusion_l2_confidence'     => 'decimal:4',
        'fusion_l2_risk'           => 'decimal:4',
        'gate_max_discharge'       => 'json',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

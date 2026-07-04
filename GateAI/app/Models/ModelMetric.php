<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelMetric extends Model
{
    protected $table = 'model_metrics';

    public $timestamps = false;

    protected $fillable = [
        'edge_node_id',
        'reservoir_id',
        'metric_time',

        // 维度一：预测准确性
        'water_level_mae_24h',
        'flow_mae_24h',
        'physics_correction_rate',
        'trend_accuracy',
        'prediction_score',

        // 维度二：决策可靠性
        'safety_override_rate',
        'decision_level_dist',
        'shadow_risk_pass_rate',
        'smooth_filter_rate',
        'decision_score',

        // 维度三：物理合规性
        'avg_physics_violation',
        'gate_limit_touch_rate',
        'rate_limit_exceed_rate',
        'compliance_score',

        // 综合
        'overall_score',
        'health_grade',
        'created_at',
    ];

    protected $casts = [
        'metric_time'           => 'datetime',
        'decision_level_dist'   => 'json',

        'water_level_mae_24h'    => 'decimal:4',
        'flow_mae_24h'           => 'decimal:2',
        'physics_correction_rate' => 'decimal:4',
        'trend_accuracy'         => 'decimal:4',
        'prediction_score'       => 'decimal:4',
        'safety_override_rate'   => 'decimal:4',
        'shadow_risk_pass_rate'  => 'decimal:4',
        'smooth_filter_rate'     => 'decimal:4',
        'decision_score'         => 'decimal:4',
        'avg_physics_violation'  => 'decimal:4',
        'gate_limit_touch_rate'  => 'decimal:4',
        'rate_limit_exceed_rate' => 'decimal:4',
        'compliance_score'       => 'decimal:4',
        'overall_score'          => 'decimal:4',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }

    public function edgeNode()
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }
}

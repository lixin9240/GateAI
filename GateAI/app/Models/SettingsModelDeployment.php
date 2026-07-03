<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingsModelDeployment extends Model
{
    protected $table = 'settings_model_deployments';

    protected $fillable = [
        'model_id',      // 模型ID
        'edge_node_id',  // 目标边缘节点ID
        'status',        // 部署状态
        'strategy',      // 部署策略
        'scheduled_at',  // 计划部署时间
        'batch_size',    // 批次大小
        'error_msg',     // 错误信息
        'deployed_by',   // 部署人
        'md5_verified',  // MD5是否验证通过
        'rollback_to',   // 回滚至模型ID
        'completed_at',  // 完成时间
    ];

    protected $casts = [
        'md5_verified' => 'integer',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function model()
    {
        return $this->belongsTo(SettingsModel::class, 'model_id');
    }

    public function edgeNode()
    {
        return $this->belongsTo(EdgeNode::class, 'edge_node_id');
    }

    public function deployer()
    {
        return $this->belongsTo(User::class, 'deployed_by');
    }

    public function rollbackTarget()
    {
        return $this->belongsTo(SettingsModel::class, 'rollback_to');
    }
}

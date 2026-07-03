<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingsModel extends Model
{
    protected $table = 'settings_models';

    protected $fillable = [
        'name',              // 模型名称
        'version',           // 版本号
        'type',              // 模型类型
        'framework',         // 框架
        'status',            // 状态
        'accuracy',          // 准确率（%）
        'f1_score',          // F1分数
        'training_date',     // 训练日期
        'training_dataset',  // 训练数据集
        'size',              // 模型大小（MB）
        'file_path',         // 文件路径
        'md5',               // MD5校验
        'tags',              // 标签
        'is_active',         // 是否激活
        'deployed_nodes',    // 已下发节点数
        'previous_model_id', // 上一版本模型ID
        'deploy_status',     // 部署状态
        'deployed_at',       // 激活时间
        'deploy_nodes',      // 下发节点列表
        'validation_report', // 验证报告
        'created_by',        // 上传人
    ];

    protected $casts = [
        'accuracy'       => 'decimal:2',
        'f1_score'       => 'decimal:4',
        'training_date'  => 'date',
        'size'           => 'integer',
        'is_active'      => 'integer',
        'deployed_nodes' => 'integer',
        'tags'           => 'json',
        'deploy_nodes'   => 'json',
        'validation_report' => 'json',
        'deployed_at'    => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function previousModel()
    {
        return $this->belongsTo(self::class, 'previous_model_id');
    }

    public function deployments()
    {
        return $this->hasMany(SettingsModelDeployment::class, 'model_id');
    }
}

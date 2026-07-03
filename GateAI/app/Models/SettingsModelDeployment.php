<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingsModelDeployment extends Model
{
    protected $table = 'settings_model_deployments';

    protected $fillable = [
        'model_id',
        'edge_node_id',
        'status',
        'strategy',
        'scheduled_at',
        'batch_size',
        'error_msg',
        'deployed_by',
        'md5_verified',
        'rollback_to',
        'completed_at',
    ];

    protected $casts = [
        'id'            => 'integer',
        'model_id'      => 'integer',
        'edge_node_id'  => 'integer',
        'batch_size'    => 'integer',
        'md5_verified'  => 'integer',
        'deployed_by'   => 'integer',
        'rollback_to'   => 'integer',
        'scheduled_at'  => 'datetime',
        'completed_at'  => 'datetime',
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

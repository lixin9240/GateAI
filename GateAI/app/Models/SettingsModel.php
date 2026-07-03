<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingsModel extends Model
{
    protected $table = 'settings_models';

    protected $fillable = [
        'name',
        'version',
        'type',
        'framework',
        'status',
        'accuracy',
        'f1_score',
        'training_date',
        'training_dataset',
        'size',
        'file_path',
        'md5',
        'tags',
        'is_active',
        'deployed_nodes',
        'previous_model_id',
        'deploy_status',
        'deployed_at',
        'deploy_nodes',
        'validation_report',
        'created_by',
    ];

    protected $casts = [
        'id'                => 'integer',
        'accuracy'          => 'decimal:2',
        'f1_score'          => 'decimal:4',
        'training_date'     => 'date',
        'size'              => 'integer',
        'is_active'         => 'integer',
        'deployed_nodes'    => 'integer',
        'previous_model_id' => 'integer',
        'created_by'        => 'integer',
        'tags'              => 'json',
        'deploy_nodes'      => 'json',
        'validation_report' => 'json',
        'deployed_at'       => 'datetime',
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

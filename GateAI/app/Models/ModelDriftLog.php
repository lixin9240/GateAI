<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelDriftLog extends Model
{
    protected $table = 'model_drift_logs';

    public $timestamps = false;

    protected $fillable = [
        'reservoir_id',
        'drift_score',
        'drift_level',
        'affected_features',
        'detected_at',
    ];

    protected $casts = [
        'affected_features' => 'json',
        'detected_at'       => 'datetime',
        'drift_score'       => 'decimal:4',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class, 'reservoir_id');
    }
}

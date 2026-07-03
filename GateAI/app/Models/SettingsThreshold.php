<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingsThreshold extends Model
{
    protected $table = 'settings_thresholds';

    protected $fillable = [
        'reservoir_id',
        'metric',
        'equipment_type',
        'warning_upper',
        'warning_lower',
        'critical_upper',
        'critical_lower',
        'debounce_seconds',
        'enabled',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'id'               => 'integer',
        'reservoir_id'     => 'integer',
        'warning_upper'    => 'decimal:4',
        'warning_lower'    => 'decimal:4',
        'critical_upper'   => 'decimal:4',
        'critical_lower'   => 'decimal:4',
        'debounce_seconds' => 'integer',
        'enabled'          => 'integer',
        'updated_by'       => 'integer',
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

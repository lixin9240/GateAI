<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingsWeight extends Model
{
    protected $table = 'settings_weights';

    protected $fillable = [
        'version',
        'enabled',
        'power_weight',
        'safety_weight',
        'ecology_weight',
        'preset_name',
        'is_preset',
        'sync_status',
        'synced_at',
        'synced_nodes',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'id'            => 'integer',
        'enabled'       => 'integer',
        'is_preset'     => 'integer',
        'power_weight'  => 'decimal:2',
        'safety_weight' => 'decimal:2',
        'ecology_weight' => 'decimal:2',
        'synced_nodes'  => 'json',
        'synced_at'     => 'datetime',
        'updated_by'    => 'integer',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

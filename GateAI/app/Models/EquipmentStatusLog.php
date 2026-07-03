<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentStatusLog extends Model
{
    protected $table = 'equipment_status_logs';

    protected $fillable = [
        'equipment_id',
        'previous_status',
        'current_status',
        'reason',
        'operator',
        'changed_at',
        'changed_by',
        'ip_address',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}

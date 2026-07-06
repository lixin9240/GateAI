<?php

namespace App\Models;

use App\Models\Concerns\HasBeijingTime;
use Illuminate\Database\Eloquent\Model;

class PowerUnit extends Model
{
    use HasBeijingTime;

    protected $table = 'power_units';

    protected $fillable = [
        'reservoir_id',
        'name',
        'code',
        'type',
        'installed_capacity',
        'status',
        'current_output',
        'manufacturer',
        'model',
        'commission_date',
        'last_synced_at',
    ];

    protected $casts = [
        'installed_capacity' => 'decimal:2',
        'current_output'     => 'decimal:2',
        'commission_date'    => 'date',
        'last_synced_at'     => 'datetime',
    ];

    public function reservoir()
    {
        return $this->belongsTo(Reservoir::class);
    }
}

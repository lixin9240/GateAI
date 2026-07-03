<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservoir extends Model
{
    use SoftDeletes;

    protected $table = 'reservoirs';

    protected $fillable = [
        'name', 'code', 'type',
        'dead_water_level', 'normal_water_level', 'flood_limit_level',
        'design_flood_level', 'check_flood_level',
        'total_capacity', 'installed_capacity', 'ecological_flow',
        'location_lat', 'location_lng', 'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'dead_water_level'    => 'decimal:2',
        'normal_water_level'  => 'decimal:2',
        'flood_limit_level'   => 'decimal:2',
        'design_flood_level'  => 'decimal:2',
        'check_flood_level'   => 'decimal:2',
        'total_capacity'      => 'decimal:2',
        'installed_capacity'  => 'decimal:2',
        'ecological_flow'     => 'decimal:2',
        'location_lat'        => 'decimal:6',
        'location_lng'        => 'decimal:6',
    ];
}

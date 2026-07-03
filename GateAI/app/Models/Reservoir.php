<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservoir extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'dead_water_level',
        'normal_water_level',
        'flood_limit_level',
        'design_flood_level',
        'check_flood_level',
        'total_capacity',
        'installed_capacity',
        'ecological_flow',
        'location_lat',
        'location_lng',
        'status',
    ];
}

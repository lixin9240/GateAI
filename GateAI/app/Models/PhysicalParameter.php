<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhysicalParameter extends Model
{
    protected $table = 'physical_parameters';

    protected $fillable = [
        'reservoir_id', // 水库ID
        'water_level', // 水位
        'surface_area', // 表面面积
        'max_discharge', // 最大流量
        'remark', // 备注
    ];

    protected $casts = [
        'water_level'    => 'float',
        'surface_area'   => 'float',
        'max_discharge'  => 'float',
    ];
}

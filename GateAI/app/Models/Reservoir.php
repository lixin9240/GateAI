<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservoir extends Model
{
    protected $table = 'reservoirs';

    protected $fillable = [
        'name',               // 水库名称
        'code',               // 水库编码
        'type',               // 水库类型
        'dead_water_level',   // 死水位（m）
        'normal_water_level', // 正常蓄水位（m）
        'flood_limit_level',  // 防洪限制水位（m）
        'design_flood_level', // 设计洪水位（m）
        'check_flood_level',  // 校核洪水位（m）
        'total_capacity',     // 总库容（万m³）
        'installed_capacity', // 装机容量（kW）
        'ecological_flow',    // 生态流量（m³/s）
        'location_lat',       // 纬度
        'location_lng',       // 经度
        'status',             // active / inactive / maintenance
    ];

    protected $casts = [
        'dead_water_level'   => 'decimal:2',
        'normal_water_level' => 'decimal:2',
        'flood_limit_level'  => 'decimal:2',
        'design_flood_level' => 'decimal:2',
        'check_flood_level'  => 'decimal:2',
        'total_capacity'     => 'decimal:2',
        'installed_capacity' => 'decimal:2',
        'ecological_flow'    => 'decimal:2',
        'location_lat'       => 'decimal:6',
        'location_lng'       => 'decimal:6',
    ];

    public function edgeNodes()
    {
        return $this->hasMany(EdgeNode::class, 'reservoir_id');
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'reservoir_id');
    }
}

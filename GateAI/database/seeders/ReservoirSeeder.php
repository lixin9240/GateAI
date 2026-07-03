<?php

namespace Database\Seeders;

use App\Models\Reservoir;
use Illuminate\Database\Seeder;

class ReservoirSeeder extends Seeder
{
    /**
     * 模拟仿真平台 —— 上下游水库种子数据
     */
    public function run(): void
    {
        $now = now();

        $reservoirs = [
            [
                'name'               => '三峡水库',
                'code'               => 'SX_RES_001',
                'type'               => 'seasonal',
                'dead_water_level'   => 145.0,
                'normal_water_level' => 175.0,
                'flood_limit_level'  => 145.0,
                'design_flood_level' => 180.4,
                'check_flood_level'  => 181.7,
                'total_capacity'     => 39300,
                'installed_capacity' => 22500,
                'ecological_flow'    => 3500,
                'location_lat'       => 30.8235,
                'location_lng'       => 111.0036,
                'status'             => 'active',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'name'               => '溪洛渡水库',
                'code'               => 'XLD_RES_001',
                'type'               => 'seasonal',
                'dead_water_level'   => 540.0,
                'normal_water_level' => 600.0,
                'flood_limit_level'  => 560.0,
                'design_flood_level' => 606.0,
                'check_flood_level'  => 609.5,
                'total_capacity'     => 12670,
                'installed_capacity' => 13860,
                'ecological_flow'    => 1200,
                'location_lat'       => 28.2417,
                'location_lng'       => 103.6358,
                'status'             => 'active',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'name'               => '向家坝水库',
                'code'               => 'XJB_RES_001',
                'type'               => 'daily_regulation',
                'dead_water_level'   => 370.0,
                'normal_water_level' => 380.0,
                'flood_limit_level'  => 370.0,
                'design_flood_level' => 384.0,
                'check_flood_level'  => 387.3,
                'total_capacity'     => 5163,
                'installed_capacity' => 7750,
                'ecological_flow'    => 600,
                'location_lat'       => 28.6417,
                'location_lng'       => 104.3874,
                'status'             => 'active',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'name'                => '上游模拟水库',
                'code'                => 'RES-UP-001',
                'type'                => 'simulation',
                'dead_water_level'    => 1.50,
                'normal_water_level'  => 3.00,
                'flood_limit_level'   => 3.50,
                'design_flood_level'  => 4.00,
                'check_flood_level'   => 4.50,
                'total_capacity'      => 0.80,
                'installed_capacity'  => 0.50,
                'ecological_flow'     => 0.05,
                'location_lat'        => 23.129110,
                'location_lng'        => 113.264380,
                'status'              => 'active',
            ],
            [
                'name'                => '下游模拟水库',
                'code'                => 'RES-DOWN-001',
                'type'                => 'simulation',
                'dead_water_level'    => 0.80,
                'normal_water_level'  => 1.80,
                'flood_limit_level'   => 2.20,
                'design_flood_level'  => 2.80,
                'check_flood_level'   => 3.20,
                'total_capacity'      => 1.20,
                'installed_capacity'  => 0.30,
                'ecological_flow'     => 0.03,
                'location_lat'        => 23.128500,
                'location_lng'        => 113.265100,
                'status'              => 'active',
            ],
        ];

        foreach ($reservoirs as $data) {
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            Reservoir::create($data);
        }
    }
}

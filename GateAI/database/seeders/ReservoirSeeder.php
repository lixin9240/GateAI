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

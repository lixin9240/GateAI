<?php

namespace Database\Seeders;

use App\Models\PowerUnit;
use Illuminate\Database\Seeder;

class PowerUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            // 三峡水库 — 3台机组
            ['reservoir_id' => 1, 'name' => '1号发电机组', 'code' => 'SX_GEN_001', 'installed_capacity' => 7500, 'status' => 'online', 'manufacturer' => '东方电机', 'model' => 'SF750-80/19800', 'commission_date' => '2003-06-01'],
            ['reservoir_id' => 1, 'name' => '2号发电机组', 'code' => 'SX_GEN_002', 'installed_capacity' => 7500, 'status' => 'online', 'manufacturer' => '东方电机', 'model' => 'SF750-80/19800', 'commission_date' => '2003-07-01'],
            ['reservoir_id' => 1, 'name' => '3号发电机组', 'code' => 'SX_GEN_003', 'installed_capacity' => 7500, 'status' => 'maintenance', 'manufacturer' => '哈尔滨电机', 'model' => 'SF750-80/19800', 'commission_date' => '2003-08-01'],

            // 溪洛渡水库 — 2台机组
            ['reservoir_id' => 2, 'name' => '1号发电机组', 'code' => 'XLD_GEN_001', 'installed_capacity' => 6930, 'status' => 'online', 'manufacturer' => '东方电机', 'model' => 'SF693-72/18900', 'commission_date' => '2013-06-01'],
            ['reservoir_id' => 2, 'name' => '2号发电机组', 'code' => 'XLD_GEN_002', 'installed_capacity' => 6930, 'status' => 'online', 'manufacturer' => '东方电机', 'model' => 'SF693-72/18900', 'commission_date' => '2013-07-01'],

            // 向家坝水库 — 2台机组
            ['reservoir_id' => 3, 'name' => '1号发电机组', 'code' => 'XJB_GEN_001', 'installed_capacity' => 3875, 'status' => 'online', 'manufacturer' => '哈尔滨电机', 'model' => 'SF387-68/17600', 'commission_date' => '2012-10-01'],
            ['reservoir_id' => 3, 'name' => '2号发电机组', 'code' => 'XJB_GEN_002', 'installed_capacity' => 3875, 'status' => 'offline', 'manufacturer' => '哈尔滨电机', 'model' => 'SF387-68/17600', 'commission_date' => '2012-11-01'],

            // 上游模拟水库 — 1台
            ['reservoir_id' => 4, 'name' => '模拟发电机组', 'code' => 'SIM_GEN_UP', 'installed_capacity' => 0.5, 'status' => 'online', 'manufacturer' => '实验设备', 'model' => 'MINI-HYDRO', 'commission_date' => '2026-01-01'],
        ];

        foreach ($units as $data) {
            PowerUnit::create($data);
        }
    }
}

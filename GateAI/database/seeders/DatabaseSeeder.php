<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RolePagePermissionSeeder::class,
            UserSeeder::class,
            ReservoirSeeder::class,
            EdgeNodeSeeder::class,
            AlarmSeeder::class,
            EquipmentSeeder::class,
            MonitoringDataSeeder::class,
            RecentMonitoringDataSeeder::class,
            PhysicsGuardConfigSeeder::class,
            ModelMetricSeeder::class,
            GateInterlockRuleSeeder::class,
            PowerUnitSeeder::class,
        ]);
    }
}

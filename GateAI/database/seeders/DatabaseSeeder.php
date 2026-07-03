<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ReservoirSeeder::class,
            EdgeNodeSeeder::class,
            AlarmSeeder::class,
            EquipmentSeeder::class,
            MonitoringDataSeeder::class,
        ]);
    }
}

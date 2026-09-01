<?php

namespace Database\Seeders;

use App\Services\GYZ\RolePermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePagePermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('role_page_permissions')->truncate();
        DB::table('role_page_permissions')->insert(RolePermissionService::buildDefaultInserts());
    }
}

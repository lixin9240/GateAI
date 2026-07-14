<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 先将所有 'active' 修正为 'online'
        DB::table('equipment')->where('status', 'active')->update(['status' => 'online']);

        // 2. 传感器：每个水库3个 → 上游液位计(离线)、下游液位计(故障)、流量计(在线)
        DB::statement("
            UPDATE equipment SET status = 'offline'
            WHERE code LIKE 'SEN-ULS-UP-%'
        ");
        DB::statement("
            UPDATE equipment SET status = 'fault'
            WHERE code LIKE 'SEN-ULS-DN-%'
        ");

        // 3. 执行器：每个水库3个 → 电动推杆(在线保持)、闸门板(维护中)、隔板(离线)
        DB::statement("
            UPDATE equipment SET status = 'maintenance'
            WHERE code LIKE 'ACT-AGP-%'
        ");
        DB::statement("
            UPDATE equipment SET status = 'offline'
            WHERE code LIKE 'ACT-DIV-%'
        ");

        // 4. PLC 组：每个水库3个 → S7控制器(在线保持)、模拟量模块(故障)、串口转换器(离线)
        DB::statement("
            UPDATE equipment SET status = 'fault'
            WHERE code LIKE 'PLC-AE04-%'
        ");
        DB::statement("
            UPDATE equipment SET status = 'offline'
            WHERE code LIKE 'COM-UG-%'
        ");

        // 5. 边缘AI(1个) → 在线保持，不做修改

        // 6. 供电(2个) → 明纬电源(在线保持)、降压模块(离线)
        DB::statement("
            UPDATE equipment SET status = 'offline'
            WHERE code LIKE 'PWR-BUCK-%'
        ");

        // 7. 水循环(4个) → 水泵(故障)、蓄水池(在线保持)、接头(维护中)、软管(离线)
        DB::statement("
            UPDATE equipment SET status = 'fault'
            WHERE code LIKE 'WTR-PUMP-%'
        ");
        DB::statement("
            UPDATE equipment SET status = 'maintenance'
            WHERE code LIKE 'WTR-FIT-%'
        ");
        DB::statement("
            UPDATE equipment SET status = 'offline'
            WHERE code LIKE 'WTR-HOSE-%'
        ");
    }

    public function down(): void
    {
        // 回滚：将所有设备恢复为 online
        DB::table('equipment')->update(['status' => 'online']);
    }
};

<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * 角色初始数据 —— 对应 roles 表
     */
    public function run(): void
    {
        $roles = [
            ['name' => '系统管理员', 'code' => 'admin',          'remark' => '系统最高权限，可管理所有模块'],
            ['name' => '调度员',    'code' => 'dispatcher',     'remark' => '调度决策、指令下发、告警处置'],
            ['name' => '运维人员',  'code' => 'operator',       'remark' => '设备管理、监测数据查看'],
            ['name' => '站长',      'code' => 'station_master', 'remark' => '水库全局监控、审批确认'],
            ['name' => '算法工程师','code' => 'algorithm',      'remark' => 'AI模型管理、训练数据查看'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}

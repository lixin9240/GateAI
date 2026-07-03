<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * 用户初始数据 —— 对应 users 表
     *
     * 密码自动由 User 模型 $casts['password' => 'hashed'] 加密
     */
    public function run(): void
    {
        $roleMap = Role::pluck('id', 'code')->toArray();

        $users = [
            // ===== 正常用户 =====
            [
                'account'              => 'admin',
                'password'             => 'admin123',
                'realname'             => '系统管理员',
                'role_id'              => $roleMap['admin'],
                'phone'                => '13800000001',
                'force_change_password'=> 0,
                'is_enabled'           => 1,
            ],
            [
                'account'              => 'dispatcher',
                'password'             => 'dispatch123',
                'realname'             => '张调度',
                'role_id'              => $roleMap['dispatcher'],
                'phone'                => '13800000002',
                'force_change_password'=> 0,
                'is_enabled'           => 1,
            ],
            [
                'account'              => 'operator',
                'password'             => 'operator123',
                'realname'             => '李运维',
                'role_id'              => $roleMap['operator'],
                'phone'                => '13800000003',
                'force_change_password'=> 0,
                'is_enabled'           => 1,
            ],
            [
                'account'              => 'station_master',
                'password'             => 'station123',
                'realname'             => '王站长',
                'role_id'              => $roleMap['station_master'],
                'phone'                => '13800000004',
                'force_change_password'=> 0,
                'is_enabled'           => 1,
            ],
            [
                'account'              => 'algorithm',
                'password'             => 'algo123',
                'realname'             => '赵算法',
                'role_id'              => $roleMap['algorithm'],
                'phone'                => '13800000005',
                'force_change_password'=> 0,
                'is_enabled'           => 1,
            ],

            // ===== 边界测试用户 =====
            [
                'account'              => 'locked_user',
                'password'             => 'locked123',
                'realname'             => '已被锁定',
                'role_id'              => $roleMap['operator'],
                'phone'                => '13800000006',
                'force_change_password'=> 0,
                'login_fail_count'     => 5,
                'lock_expire_time'     => now()->addDays(1),
                'is_enabled'           => 1,
            ],
            [
                'account'              => 'disabled_user',
                'password'             => 'disabled123',
                'realname'             => '已禁用',
                'role_id'              => $roleMap['operator'],
                'phone'                => '13800000007',
                'force_change_password'=> 0,
                'is_enabled'           => 0,
            ],
            [
                'account'              => 'force_pwd',
                'password'             => 'force123',
                'realname'             => '需改密',
                'role_id'              => $roleMap['dispatcher'],
                'phone'                => '13800000008',
                'force_change_password'=> 1,
                'is_enabled'           => 1,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['account' => $user['account']],
                $user
            );
        }
    }
}

<?php

namespace App\Services\GYZ;

use App\Models\RolePagePermission;
use App\Support\LogHelper;
use Illuminate\Support\Facades\DB;

class RolePermissionService
{
    /** 系统预置页面清单 */
    public const PAGES = [
        ['pageId'=>'monitor_overview',   'pageName'=>'监控大屏',       'module'=>'告警管理','path'=>'/databoard/overview'],
        ['pageId'=>'equipment',          'pageName'=>'设备管理',       'module'=>'设备管理','path'=>'/equipment'],
        ['pageId'=>'history_query',      'pageName'=>'历史查询',       'module'=>'告警管理','path'=>null],
        ['pageId'=>'alarm_management',   'pageName'=>'告警管理',       'module'=>'告警管理','path'=>null],
        ['pageId'=>'dispatch_analysis',  'pageName'=>'调度决策',       'module'=>'系统配置','path'=>'/dispatch/analysis'],
        ['pageId'=>'virtual_simulation', 'pageName'=>'虚拟仿真',       'module'=>'虚拟仿真','path'=>'/virtual-simulation'],
        ['pageId'=>'digital_twin',       'pageName'=>'数字孪生驾驶舱', 'module'=>'数字孪生','path'=>'/simulation'],
        ['pageId'=>'user_management',    'pageName'=>'用户管理',       'module'=>'系统设置','path'=>null],
        ['pageId'=>'threshold_settings', 'pageName'=>'告警阈值配置',   'module'=>'系统设置','path'=>'/settings/thresholds'],
        ['pageId'=>'profile',            'pageName'=>'个人中心',       'module'=>'个人中心','path'=>'/profile'],
        ['pageId'=>'model_management',   'pageName'=>'模型管理',       'module'=>'系统设置','path'=>'/settings/models'],
        ['pageId'=>'weight_settings',    'pageName'=>'权重配置',       'module'=>'系统设置','path'=>'/settings/weights'],
        ['pageId'=>'security_monitor',   'pageName'=>'安防监控',       'module'=>'监控大屏','path'=>'/security'],
    ];

    /**
     * 获取权限配置（查询）
     */
    public function getConfig(): array
    {
        // 所有角色
        $allRoles = \App\Models\Role::pluck('name')->toArray();

        // 当前权限映射: page_id => [role_names]
        $permissions = RolePagePermission::all()->groupBy('page_id')
            ->map(fn($rows) => $rows->pluck('role_name')->toArray());

        // 组装页面列表
        $pages = [];
        foreach (self::PAGES as $page) {
            $authorized = $permissions[$page['pageId']] ?? [];
            $pages[] = [
                'pageId'              => $page['pageId'],
                'pageName'            => $page['pageName'],
                'module'              => $page['module'],
                'path'                => $page['path'],
                'authorizedRoleNames' => $authorized,
            ];
        }

        return [
            'allRoles' => $allRoles,
            'pages'    => $pages,
        ];
    }

    /**
     * 保存权限配置（全量覆盖）
     */
    public function saveConfig(array $permissions, int $userId): void
    {
        DB::transaction(function () use ($permissions) {
            RolePagePermission::query()->delete();

            $inserts = [];
            foreach ($permissions as $p) {
                foreach ($p['roleNames'] as $roleName) {
                    $inserts[] = [
                        'page_id'    => $p['pageId'],
                        'role_name'  => $roleName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
            if (! empty($inserts)) {
                RolePagePermission::insert($inserts);
            }
        });

        LogHelper::business('角色页面权限已保存', [
            'user_id'     => $userId,
            'page_count'  => count($permissions),
            'total_rules' => count($permissions),
        ], 'info', 'ROLE_PERMISSION_SAVE');
    }

    /**
     * 重置权限配置（恢复默认：全部角色全选，写入数据库）
     */
    public function resetConfig(int $userId): array
    {
        RolePagePermission::query()->delete();
        RolePagePermission::insert($this->buildDefaultInserts());

        LogHelper::business('角色页面权限已重置为默认', [
            'user_id' => $userId,
        ], 'warning', 'ROLE_PERMISSION_RESET');

        return $this->getConfig();
    }

    /**
     * 构建默认权限数据（所有角色 × 所有页面）
     * 供 Seeder 和 resetConfig 共用
     */
    public static function buildDefaultInserts(): array
    {
        $allRoles = \App\Models\Role::pluck('name')->toArray();
        $now = now();

        $inserts = [];
        foreach (self::PAGES as $page) {
            foreach ($allRoles as $roleName) {
                $inserts[] = [
                    'page_id'    => $page['pageId'],
                    'role_name'  => $roleName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        return $inserts;
    }
}

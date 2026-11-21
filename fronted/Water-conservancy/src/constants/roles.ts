/** 系统名称 */
export const APP_TITLE = '水电站阀门智能调度系统'

/** 用户角色 */
export type UserRole = 'operator' | 'dispatcher' | 'manager' | 'admin' | 'algorithm_engineer'

/** 路由权限矩阵 */
export const ROUTE_ROLES: Record<string, UserRole[]> = {
  // ═══ 监控大屏 ═══
  '/dashboard/overview': ['operator', 'dispatcher', 'manager', 'admin', 'algorithm_engineer'],
  '/dashboard/hydrology': ['operator', 'dispatcher', 'manager', 'admin'],
  '/dashboard/gate': ['operator', 'dispatcher', 'manager', 'admin'],
  '/dashboard/power': ['operator', 'dispatcher', 'manager', 'admin'],
  '/dashboard/security': ['operator', 'manager', 'admin'],
  '/hydrology/virtual-sim': ['operator', 'dispatcher', 'manager', 'admin', 'algorithm_engineer'],

  // ═══ 历史查询 ═══
  '/history': ['dispatcher', 'manager', 'admin'],
  '/history/query': ['dispatcher', 'manager', 'admin'],
  '/history/compare': ['dispatcher', 'manager', 'admin'],
  '/history/model-score': ['dispatcher', 'manager', 'admin', 'algorithm_engineer'],
  '/history/interlock': ['dispatcher', 'manager', 'admin'],

  // ═══ 告警 / 调度 / 仿真 ═══
  '/warning': ['operator', 'dispatcher', 'manager', 'admin'],
  '/dispatch': ['dispatcher', 'manager', 'admin', 'algorithm_engineer'],
  '/dispatch/analysis': ['dispatcher', 'manager', 'admin', 'algorithm_engineer'],
  '/dispatch/control': ['dispatcher', 'manager', 'admin', 'algorithm_engineer'],
  '/dispatch/gates': ['dispatcher', 'manager', 'admin', 'algorithm_engineer'],
  '/dispatch/gate-actions': ['dispatcher', 'manager', 'admin'],
  '/simulation': ['operator', 'dispatcher', 'manager', 'admin', 'algorithm_engineer'],
  '/virtual-simulation': ['operator', 'dispatcher', 'manager', 'admin', 'algorithm_engineer'],

  // ═══ 设备管理 ═══
  '/equipment': ['operator', 'manager', 'admin'],

  // ═══ 个人中心 — 所有人 ═══
  '/profile': ['operator', 'dispatcher', 'manager', 'admin', 'algorithm_engineer'],

  // ═══ 系统设置入口 ═══
  '/settings': ['admin', 'manager'],

  // ── 基础运维配置 — 管理员 + 站长 ──
  '/settings/thresholds': ['admin', 'manager'],
  '/settings/weights': ['admin', 'manager'],
  '/settings/models': ['admin', 'manager', 'algorithm_engineer'],
  '/settings/users': ['admin', 'manager'],

  // ── 物理防护 — 管理员 + 站长 ──
  '/settings/physics-guard': ['admin', 'manager'],
  '/settings/physics-guard-history': ['admin', 'manager'],

  // ── AI 模型健康度 — 管理员 + 站长 + 算法工程师 ──
  '/settings/ai/metrics': ['admin', 'manager', 'algorithm_engineer'],
  '/settings/ai/compare': ['admin', 'manager', 'algorithm_engineer'],

  // ── 阀门互锁 — 管理员 + 站长 ──
  '/settings/gate-interlock': ['admin', 'manager'],
  '/settings/gate-interlock/logs': ['admin', 'manager'],
}

/** 侧边栏菜单 */
export interface MenuItem {
  path: string
  title: string
  icon: string
  children?: MenuItem[]
}

export const MENU_ITEMS: MenuItem[] = [
  {
    path: '/dashboard',
    title: '监控大屏',
    icon: 'Monitor',
    children: [
      { path: '/dashboard/overview', title: '综合概览', icon: 'Monitor' },
      { path: '/dashboard/hydrology', title: '水情监测', icon: 'Ship' },
      { path: '/dashboard/gate', title: '阀门检测', icon: 'Switch' },
      { path: '/dashboard/power', title: '发电检测', icon: 'DataAnalysis' },
      { path: '/dashboard/security', title: '安防检测', icon: 'VideoCamera' },
    ],
  },
  { path: '/dispatch', title: '调度决策', icon: 'Operation' },
  { path: '/virtual-simulation', title: '虚拟仿真', icon: 'SetUp' },
  { path: '/simulation', title: '数字孪生', icon: 'Cpu' },
  { path: '/warning', title: '告警管理', icon: 'Warning' },
  { path: '/equipment', title: '设备管理', icon: 'SetUp' },
  {
    path: '/settings/models',
    title: '模型管理',
    icon: 'DataAnalysis',
    children: [
      { path: '/settings/ai/metrics', title: '模型健康度', icon: 'Setting' },
      { path: '/settings/models', title: '模型版本管理', icon: 'Setting' },
      { path: '/settings/ai/compare', title: '模型版本对比', icon: 'Setting' },
      { path: '/history/model-score', title: '模型评分历史', icon: 'Clock' },
    ],
  },
  {
    path: '/history',
    title: '历史查询',
    icon: 'Clock',
    children: [
      { path: '/history/query', title: '数据查询', icon: 'Clock' },
      { path: '/history/compare', title: '双时段对比', icon: 'Clock' },
      { path: '/history/interlock', title: '互锁事件回溯', icon: 'Clock' },
      { path: '/settings/physics-guard-history', title: '配置变更历史', icon: 'Setting' },
      { path: '/settings/gate-interlock/logs', title: '互锁触发日志', icon: 'Setting' },
    ],
  },
  {
    path: '/settings',
    title: '系统配置',
    icon: 'Setting',
    children: [
      { path: '/settings/thresholds', title: '告警阈值配置', icon: 'Setting' },
      { path: '/settings/weights', title: '多目标权重配置', icon: 'Setting' },
      { path: '/settings/physics-guard', title: '物理防护配置', icon: 'Setting' },
      { path: '/settings/gate-interlock', title: '阀门互锁规则', icon: 'Setting' },
      { path: '/settings/users', title: '用户管理', icon: 'Setting' },
    ],
  },
  { path: '/profile', title: '个人中心', icon: 'User' },
]

/** 驾驶舱三页 — 横向快捷导航（不与侧边栏重复） */
export const COCKPIT_MODULE_PATHS = ['/warning', '/dispatch', '/simulation'] as const

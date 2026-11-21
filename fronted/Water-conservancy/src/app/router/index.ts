// ============================================================
// Vue Router — 九大板块路由
// ============================================================
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router'
import { ElMessage } from 'element-plus'
import { APP_TITLE, ROUTE_ROLES } from '@/constants/roles'
import { FORCE_PWD_CHANGE_KEY } from '@/constants/auth'
import { useUserStore } from '@/stores/user'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/login/LoginPage.vue'),
    meta: { title: '登录', requiresAuth: false },
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    redirect: '/dashboard/overview',
    children: [
      {
        path: 'dashboard',
        redirect: '/dashboard/overview',
      },
      {
        path: 'dashboard/overview',
        name: 'DashboardOverview',
        component: () => import('@/views/dashboard/DashboardPage.vue'),
        meta: { title: '综合概览' },
      },
      {
        path: 'dashboard/hydrology',
        name: 'Hydrology',
        component: () => import('@/views/dashboard/HydrologyPage.vue'),
        meta: { title: '水情监测' },
      },
      {
        path: 'dashboard/gate',
        name: 'Gate',
        component: () => import('@/views/dashboard/GatePage.vue'),
        meta: { title: '阀门检测' },
      },
      {
        path: 'dashboard/power',
        name: 'Power',
        component: () => import('@/views/dashboard/PowerPage.vue'),
        meta: { title: '发电检测' },
      },
      {
        path: 'dashboard/security',
        name: 'Security',
        component: () => import('@/views/dashboard/SecurityPage.vue'),
        meta: { title: '安防检测' },
      },
      {
        path: 'history',
        component: () => import('@/views/history/index.vue'),
        meta: { title: '历史查询' },
        redirect: '/history/query',
        children: [
          {
            path: 'query',
            name: 'HistoryQuery',
            component: () => import('@/views/history/components/DataQueryTab.vue'),
            meta: { title: '数据查询' },
          },
          {
            path: 'compare',
            name: 'HistoryCompare',
            component: () => import('@/views/history/components/DualCompareTab.vue'),
            meta: { title: '双时段对比' },
          },
          {
            path: 'model-score',
            name: 'HistoryModelScore',
            component: () => import('@/views/history/components/ModelScoreTab.vue'),
            meta: { title: '模型评分历史' },
          },
          {
            path: 'interlock',
            name: 'HistoryInterlock',
            component: () => import('@/views/history/components/InterlockEventTab.vue'),
            meta: { title: '互锁事件回溯' },
          },
        ],
      },
      {
        path: 'hydrology/virtual-sim',
        redirect: '/virtual-simulation',
      },
      {
        path: 'warning',
        name: 'Warning',
        component: () => import('@/views/warning/WarningPage.vue'),
        meta: { title: '告警管理' },
      },
      {
        path: 'dispatch',
        component: () => import('@/views/dispatch/index.vue'),
        meta: { title: '调度决策' },
        redirect: '/dispatch/control',
        children: [
          {
            path: 'analysis',
            name: 'DispatchAnalysis',
            component: () => import('@/views/dispatch/DispatchAnalysisPage.vue'),
            meta: { title: '决策分析' },
          },
          {
            path: 'control',
            name: 'DispatchControl',
            component: () => import('@/views/dispatch/DispatchControlPage.vue'),
            meta: { title: '运行控制' },
          },
          {
            path: 'gates',
            name: 'DispatchGates',
            component: () => import('@/views/dispatch/DispatchGatesPage.vue'),
            meta: { title: '节点控制' },
          },
        ],
      },
      {
        path: 'virtual-simulation',
        name: 'VirtualSimulation',
        component: () => import('@/views/hydrology/VirtualSimulationPage.vue'),
        meta: { title: '虚拟仿真', shortTitle: '虚拟仿真' },
      },
      {
        path: 'simulation',
        name: 'Simulation',
        component: () => import('@/views/simulation/SimulationPage.vue'),
        meta: {
          title: '智慧水利数字孪生驾驶舱 - 向家坝水电站阀门智能调度系统',
          shortTitle: '数字孪生',
        },
      },
      {
        path: 'equipment',
        name: 'Equipment',
        component: () => import('@/views/equipment/EquipmentPage.vue'),
        meta: { title: '设备管理' },
      },
      {
        path: 'settings',
        name: 'Settings',
        redirect: '/settings/thresholds',
      },
      {
        path: 'settings/thresholds',
        name: 'SettingsThresholds',
        component: () => import('@/views/settings/SettingsPage.vue'),
        meta: { title: '告警阈值配置', settingsTab: 'thresholds' },
      },
      // 多目标权重配置页面已隐藏（后续可能恢复，先注释掉）
      /*
      {
        path: 'settings/weights',
        name: 'SettingsWeights',
        component: () => import('@/views/settings/SettingsPage.vue'),
        meta: { title: '多目标权重配置', settingsTab: 'weights' },
      },
      */
      {
        path: 'settings/models',
        name: 'SettingsModels',
        component: () => import('@/views/settings/SettingsPage.vue'),
        meta: { title: '模型管理', settingsTab: 'models' },
      },
      {
        path: 'settings/users',
        name: 'SettingsUsers',
        component: () => import('@/views/settings/SettingsPage.vue'),
        meta: { title: '用户管理', settingsTab: 'users' },
      },
      {
        path: 'settings/role-pages',
        name: 'SettingsRolePages',
        component: () => import('@/views/settings/SettingsPage.vue'),
        meta: { title: '角色页面权限', settingsTab: 'role-pages' },
      },
      {
        path: 'settings/physics-guard',
        name: 'SettingsPhysicsGuard',
        component: () => import('@/views/settings/SettingsPage.vue'),
        meta: { title: '物理防护配置', settingsTab: 'physics-guard' },
      },
      {
        path: 'settings/physics-guard-history',
        name: 'SettingsPhysicsGuardHistory',
        component: () => import('@/views/settings/SettingsPage.vue'),
        meta: { title: '配置变更历史', settingsTab: 'physics-guard-history' },
      },
      {
        path: 'settings/ai/models',
        name: 'SettingsAiModels',
        redirect: '/settings/models',
        meta: { title: '模型管理' },
      },
      {
        path: 'settings/ai/metrics',
        name: 'SettingsAiMetrics',
        component: () => import('@/views/settings/gateai/ModelMetricsPage.vue'),
        meta: { title: '模型健康度仪表盘' },
      },
      {
        path: 'settings/ai/compare',
        name: 'SettingsAiCompare',
        component: () => import('@/views/settings/gateai/ModelComparePage.vue'),
        meta: { title: '模型版本对比' },
      },
      {
        path: 'settings/gate-interlock',
        name: 'SettingsGateInterlock',
        component: () => import('@/views/settings/gateai/GateInterlockPage.vue'),
        meta: { title: '阀门互锁规则配置' },
      },
      {
        path: 'settings/gate-interlock/logs',
        name: 'SettingsGateInterlockLogs',
        component: () => import('@/views/settings/gateai/GateInterlockLogsPage.vue'),
        meta: { title: '互锁触发日志' },
      },
      {
        path: 'profile',
        name: 'Profile',
        component: () => import('@/views/profile/index.vue'),
        meta: { title: '个人中心' },
      },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/components/NotFoundPage.vue'),
    meta: { title: '页面不存在', requiresAuth: false },
  },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to, _from, next) => {
  document.title = to.meta.title ? `${to.meta.title} - ${APP_TITLE}` : APP_TITLE

  const userStore = useUserStore()

  // 无效 token（有 token 但无用户信息）视为未登录
  if (userStore.token && !userStore.userInfo) {
    userStore.logout()
  }

  const isLoggedIn = userStore.isLoggedIn

  // 未登录：除登录页和 404 外，一律跳转登录
  if (to.meta.requiresAuth !== false && !isLoggedIn) {
    next({ name: 'Login', query: { redirect: to.fullPath } })
    return
  }

  // 已登录但需强制改密：只允许停留在登录页
  const needForcePwdChange = sessionStorage.getItem(FORCE_PWD_CHANGE_KEY) === 'true'
  if (isLoggedIn && needForcePwdChange) {
    if (to.name !== 'Login') {
      next({ name: 'Login' })
      return
    }
    next()
    return
  }

  // 已登录访问登录页：跳转首页
  if (to.name === 'Login' && isLoggedIn) {
    next({ path: '/dashboard/overview' })
    return
  }

  // 根路径未登录时去登录页
  if (to.path === '/' && !isLoggedIn) {
    next({ name: 'Login' })
    return
  }

  const allowedRoles = ROUTE_ROLES[to.path]
  if (allowedRoles && isLoggedIn && userStore.userInfo?.roles.length) {
    // 系统管理员硬保证：始终拥有所有页面权限
    const isAdmin = userStore.userInfo.roles.includes('admin')
    // 优先使用后端配置的 permissions 列表，回退到硬编码 ROUTE_ROLES
    const perms = userStore.userInfo.permissions
    let hasAccess: boolean
    if (isAdmin) {
      hasAccess = true
    } else if (perms && perms.length > 0) {
      hasAccess = perms.includes(to.path)
    } else {
      hasAccess = allowedRoles.some((role) => userStore.userInfo.roles.includes(role))
    }
    if (!hasAccess) {
      ElMessage.warning('您暂无该页面访问权限')
      // 防止无限重定向：回退到个人中心（所有角色均可访问）
      if (to.path === '/profile') {
        // 连个人中心都进不去 → 登出
        userStore.logout()
        next({ name: 'Login' })
      } else {
        next({ path: '/profile' })
      }
      return
    }
  }

  next()
})

export default router

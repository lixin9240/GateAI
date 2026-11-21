// ============================================================
// 系统设置 — 字典常量
// 按需求文档 5.4 / 8.4 节定义
// ============================================================

// ---------- 预设权重方案 ----------
export const WEIGHT_PRESETS = [
  { id: 'balanced', label: '均衡模式', power: 0.4, safety: 0.35, eco: 0.25, desc: '日常运行' },
  {
    id: 'power',
    label: '发电优先',
    power: 0.6,
    safety: 0.25,
    eco: 0.15,
    desc: '枯水期，最大化发电',
  },
  { id: 'safety', label: '安全优先', power: 0.15, safety: 0.7, eco: 0.15, desc: '汛期，防洪为主' },
  { id: 'eco', label: '生态优先', power: 0.2, safety: 0.25, eco: 0.55, desc: '生态调度，保障下游' },
] as const

// ---------- 告警渠道 ----------
export const ALARM_CHANNELS = [
  { label: 'Web端弹窗', value: 'web' },
  { label: '声音提示', value: 'sound' },
  { label: '短信', value: 'sms' },
  { label: '邮件', value: 'email' },
] as const

// ---------- 模型类型映射 ----------
export const MODEL_TYPE_MAP: Record<string, string> = {
  lstm_prediction: 'LSTM 预测',
  dqn_decision: 'DQN 决策',
  fault_detection: '故障检测',
  general: '通用',
}

// ---------- 模型状态映射 ----------
export const MODEL_STATUS_MAP: Record<string, string> = {
  uploaded: '已上传',
  validating: '验证中',
  ready: '就绪',
  active: '激活中',
  deprecated: '已废弃',
}

// ---------- 阈值指标映射 ----------
export const METRIC_LABEL_MAP: Record<string, string> = {
  upstream_level: '上游水位',
  downstream_level: '下游水位',
  inflow_rate: '入库流量',
  outflow_rate: '出库流量',
  gate_opening: '阀门开度',
  power_output: '发电功率',
}

// ---------- 用户角色 ----------
export const USER_ROLES: Record<string, string> = {
  operator: '运维人员',
  dispatcher: '调度员',
  manager: '站长',
  admin: '系统管理员',
  algorithm_engineer: '算法工程师',
}

export const USER_ROLE_OPTIONS = [
  { label: '系统管理员', value: 'admin' },
  { label: '调度员', value: 'dispatcher' },
  { label: '运维人员', value: 'operator' },
  { label: '站长', value: 'manager' },
  { label: '算法工程师', value: 'algorithm_engineer' },
] as const

// ---------- 系统设置 — Tab 与路由映射 ----------
export const SETTINGS_TAB_ROUTES = {
  thresholds: '/settings/thresholds',
  // weights: '/settings/weights', // 多目标权重配置页面已隐藏
  models: '/settings/models',
  users: '/settings/users',
  'physics-guard': '/settings/physics-guard',
  'physics-guard-history': '/settings/physics-guard-history',
  'ai-metrics': '/settings/ai/metrics',
  'gate-interlock': '/settings/gate-interlock',
} as const

export type SettingsTabName = keyof typeof SETTINGS_TAB_ROUTES

export function buildSettingsPath(
  tab: SettingsTabName,
  query?: Record<string, string | number | undefined>,
) {
  const q: Record<string, string> = {}
  if (query) {
    Object.entries(query).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') q[k] = String(v)
    })
  }
  return { path: SETTINGS_TAB_ROUTES[tab], query: Object.keys(q).length ? q : undefined }
}

/** 根据当前路由 path 反查 Tab 名 */
export function settingsTabFromPath(path: string): SettingsTabName | null {
  const entry = Object.entries(SETTINGS_TAB_ROUTES).find(([, p]) => p === path)
  return (entry?.[0] as SettingsTabName) ?? null
}

// ---------- 水库列表（用于下拉选择）----------
export const RESERVOIR_OPTIONS = [
  { label: '示范水库', value: 1 },
  { label: '三峡水库', value: 2 },
  { label: '溪洛渡水库', value: 3 },
  { label: '向家坝水库', value: 4 },
] as const

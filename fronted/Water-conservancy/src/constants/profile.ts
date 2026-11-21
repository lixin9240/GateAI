// ============================================================
// 个人中心 — 字典常量
// 按需求文档 6.3.3 / 8.4 节定义
// ============================================================

// ---------- 操作模块 ----------
export const OPERATION_MODULES: Record<string, string> = {
  equipment: '设备管理',
  settings: '系统设置',
  control: '阀门控制',
  dispatch: '调度决策',
  alarm: '告警处理',
  profile: '个人中心',
  auth: '登录认证',
} as const

export const OPERATION_MODULE_OPTIONS = Object.entries(OPERATION_MODULES).map(([value, label]) => ({
  label,
  value,
}))

// ---------- 操作类型 ----------
export const OPERATION_TYPES: Record<string, string> = {
  view: '查看',
  create: '新增',
  update: '修改',
  delete: '删除',
  restart: '重启',
  emergency_stop: '急停',
  export: '导出',
  login: '登录',
  status_change: '状态变更',
  execute: '执行',
  dispose: '处置',
} as const

// ---------- 头像限制 ----------
export const AVATAR_MAX_SIZE = 2 * 1024 * 1024 // 2MB
export const AVATAR_ACCEPT = '.jpg,.jpeg,.png'
export const AVATAR_SIZE = 200 // 裁剪尺寸

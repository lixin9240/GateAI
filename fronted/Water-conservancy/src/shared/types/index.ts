// ============================================================
// 全局 TypeScript 类型定义
// 字段命名与《总接口文档》保持一致
// ============================================================

// ---------- API 通用响应结构 ----------
export interface ApiResponse<T = unknown> {
  code: number
  msg: string
  data: T
  success: boolean
  trace_id: string
}

// ---------- 分页请求参数 ----------
export interface PageParams {
  page: number
  page_size: number
}

// ---------- 分页响应数据 ----------
export interface PageResult<T> {
  list: T[]
  total: number
}

// ---------- 通用下拉选项 ----------
export interface DictOption {
  label: string
  value: string | number
  color?: string
  icon?: string
}

// ---------- 字典映射表 ----------
export type DictMap = Record<string | number, DictOption>

// ════════════════════════════════════════════════════════════
// 设备管理相关类型
// ════════════════════════════════════════════════════════════

/** 设备类型 */
export type EquipmentType =
  'sensor' | 'plc' | 'gateway' | 'actuator' | 'power' | 'pump' | 'accessory'

/** 设备状态 */
export type EquipmentStatus = 'online' | 'offline' | 'fault' | 'maintenance'

/** 设备列表项 */
export interface Equipment {
  id: number
  name: string
  code: string
  type: string
  reservoir_id: number
  reservoir_name: string
  status: string
  manufacturer: string
  model: string
  health_score: number
  last_online: string
  install_location?: string
  group?: string
  responsible_person?: string
  purchase_date?: string
  remark?: string
}

/** 设备详情（含告警 + 监测数据） */
export interface EquipmentDetail {
  id: number
  name: string
  code: string
  type: EquipmentType
  reservoir_id: number
  reservoir_name: string
  status: EquipmentStatus
  manufacturer: string
  model: string
  health_score: number
  last_online: string
  // 额外详情
  install_location?: string
  ip?: string
  cpu_usage?: number
  memory_usage?: number
  // 关联数据
  current_alarms?: AlarmBrief[]
  latest_monitoring?: MonitoringSnapshot | null
}

/** 告警摘要 */
export interface AlarmBrief {
  id: number
  alarm_no: string
  level: string
  type: string
  message: string
  created_at: string
}

/** 监测数据快照 */
export interface MonitoringSnapshot {
  upstream_level: number
  downstream_level: number
  inflow_rate: number
  outflow_rate: number
  gate_opening: number
  power_output: number
  timestamp: string
}

/** 远程重启请求 */
export interface EquipmentRestartParams {
  force?: boolean
  delay?: number
  reason: string
}

/** 设备状态变更请求 */
export interface EquipmentStatusParams {
  status: EquipmentStatus
  reason?: string
}

/** 状态变更响应 */
export interface StatusChangeResult {
  id: number
  previous_status: string
  current_status: string
  changed_at: string
}

// ════════════════════════════════════════════════════════════
// 系统设置相关类型
// ════════════════════════════════════════════════════════════

/** 告警阈值配置 */
export interface ThresholdRule {
  id: number
  reservoir_id: number | null
  metric: string
  warning_upper: number
  warning_lower: number
  critical_upper: number
  critical_lower: number
  debounce_seconds: number
  enabled: number
}

/** 阈值更新请求 */
export interface ThresholdUpdateParams {
  warning_upper?: number
  warning_lower?: number
  critical_upper?: number
  critical_lower?: number
  debounce_seconds?: number
  enabled?: number
}

/** 多目标权重配置 */
export interface WeightConfig {
  id: number
  version: string
  enabled: number
  power_weight: number
  safety_weight: number
  ecology_weight: number
  preset_name: string
  is_preset: number
  updated_at: string
}

/** 权重更新请求 */
export interface WeightUpdateParams {
  enabled?: number
  power_weight: number
  safety_weight: number
  ecology_weight: number
  preset_name?: string
  description?: string
  version?: string
}

/** AI 模型 */
export interface ModelInfo {
  id: number
  name: string
  version: string
  type: string
  framework: string
  status: string
  accuracy: number
  training_date: string
  size: number
  is_active: number
  deployed_nodes: number
  /** 三维评判综合分 0~1 */
  overall_score?: number
  /** 健康等级 S/A/B/C/D */
  health_grade?: string
}

/** 模型上传参数 */
export interface ModelUploadParams {
  name: string
  version: string
  type: string
  framework?: string
  description?: string
  accuracy?: number
  training_dataset?: string
}

/** 模型激活参数 */
export interface ModelActivateParams {
  force?: boolean
  rollback_on_failure?: boolean
}

/** 模型回滚参数 */
export interface ModelRollbackParams {
  reason?: string
}

/** 模型下发参数 */
export interface ModelDeployParams {
  edge_node_ids: number[]
  strategy?: 'immediate' | 'gradual' | 'scheduled'
}

/** 系统用户 */
export interface SystemUser {
  id: number
  account: string
  realname: string
  role_id: number
  role_name: string
  phone: string
  is_enabled: number
  created_at: string
  /** 登录失败或管理员锁定到期时间（需后端在用户列表接口返回） */
  lock_expire_time?: string | null
  /** 锁定原因 */
  lock_reason?: string | null
  last_login_at?: string | null
}

/** 创建用户请求 */
export interface CreateUserParams {
  account: string
  password: string
  realname: string
  role_id: number
  phone?: string
}

/** 更新用户请求 */
export interface UpdateUserParams {
  realname?: string
  role_id?: number
  phone?: string
  is_enabled?: number
}

/** 重置密码请求 */
export interface ResetPasswordParams {
  new_password?: string
  force_logout?: boolean
}

/** 锁定账号请求 */
export interface LockUserParams {
  reason: string
  duration?: number
  force_logout?: boolean
}

/** 解锁账号请求 */
export interface UnlockUserParams {
  reason?: string
}

// ════════════════════════════════════════════════════════════
// 个人中心相关类型
// ════════════════════════════════════════════════════════════

/** 个人资料 */
export interface ProfileInfo {
  id: number
  account: string
  realname: string
  avatar?: string
  role_name: string
  phone: string
  email: string
  created_at: string
}

/** 更新资料请求 */
export interface UpdateProfileParams {
  realname?: string
  phone?: string
  email?: string
}

/** 修改密码请求 */
export interface ChangePasswordParams {
  old_password: string
  new_password: string
  confirm_password: string
}

/** 操作日志 */
export interface OperationLog {
  id: number
  time: string
  module: string
  type: string
  description: string
  result: number // 1=成功 0=失败
}

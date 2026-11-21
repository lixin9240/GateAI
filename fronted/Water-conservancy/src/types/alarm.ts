// ============================================================
// 告警管理 — TypeScript 类型定义
// 依据：《水电站阀门智能调度系统-详细需求报告》第二章
// ============================================================

// ---------- 告警级别 ----------
export type AlarmLevel = 'URGENT' | 'IMPORTANT' | 'NORMAL'

// ---------- 设备类型（筛选） ----------
export type AlarmDeviceType = 'gate' | 'hydro' | 'sensor'

// ---------- 告警类型 ----------
export type AlarmType =
  | 'HIGH_WATER'
  | 'LOW_WATER'
  | 'FLOW_SPIKE'
  | 'POWER'
  | 'DEVICE_OFFLINE'
  | 'EXEC_TIMEOUT'
  | 'EXEC_FAIL'
  | 'MODEL_HEALTH_DEGRADED'
  | 'MODEL_ROLLBACK'
  | 'MODEL_DRIFT'

// ---------- 告警处理状态 ----------
export type AlarmStatus = 'pending' | 'confirmed' | 'handled'

// ---------- 正式告警记录 ----------
export interface AlarmRecord {
  id: number
  /** 告警编号 */
  alarmNo?: string
  level: AlarmLevel
  type: AlarmType
  content: string
  threshold: number
  currentValue: number
  durationSec: number
  status: AlarmStatus
  confirmedAt: string | null
  confirmedBy: number | null
  confirmedByName: string | null
  handledAt: string | null
  handledBy: number | null
  handledByName: string | null
  remark: string | null
  createdAt: string
  /** 超限开始时间 */
  exceedStart?: string | null
  /** 最近更新时间 */
  updatedAt?: string | null
  /** 链路追踪 ID */
  traceId?: string | null
  /** 所属水库 */
  reservoirId?: number | null
  reservoirName?: string | null
  /** 监测点位名称 */
  pointName: string
  /** 设备类型 */
  deviceType: AlarmDeviceType
  /** 是否误告警 */
  isFalseAlarm?: boolean
  /** 触发时刻监测快照 */
  snapshot: AlarmSnapshot | null
}

// ---------- 触发时刻监测快照 ----------
export interface AlarmSnapshot {
  upstreamLevel: number // 上游水位 (m)
  downstreamLevel: number // 下游水位 (m)
  flowRate: number // 流量 (m³/s)
  gateOpening: number // 阀门开度 (%)
  recordedAt: string // 快照时间
}

// ---------- 超限日志（瞬时超限，不告警） ----------
export interface AlarmExceedLog {
  id: number
  point: string
  type: AlarmType
  value: number
  threshold: number
  durationSec: number
  createdAt: string
}

// ---------- 告警列表筛选参数 ----------
export interface AlarmFilterParams {
  level?: AlarmLevel
  status?: AlarmStatus
  type?: AlarmType
  deviceType?: AlarmDeviceType
  startTime?: string
  endTime?: string
  keyword?: string
  pageNum: number
  pageSize: number
}

export interface AlarmStatsResult {
  today: number
  pending: number
  handled: number
  falseAlarm: number
  levelDistribution: Record<AlarmLevel, number>
}

// ---------- 告警列表响应 ----------
export interface AlarmListResult {
  list: AlarmRecord[]
  total: number
  pageNum: number
  pageSize: number
  /** 未处理告警总数（用于角标） */
  pendingCount: number
}

// ---------- 确认告警请求 ----------
export interface AlarmConfirmParams {
  id: number
}

// ---------- 处置告警请求 ----------
export interface AlarmHandleParams {
  id: number
  remark: string
}

// ---------- WebSocket 告警推送消息 ----------
export interface AlarmPushMessage {
  type: 'alarm_new'
  data: AlarmRecord
  pendingCount: number
}

// ---------- 时间范围快捷选项 ----------
export type TimeRangePreset = 'today' | '7days' | '30days' | 'custom'

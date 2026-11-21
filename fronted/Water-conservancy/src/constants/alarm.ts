// ============================================================
// 告警管理 — 常量字典
// 依据：《水电站阀门智能调度系统-详细需求报告》§2.9
// ============================================================
import type { DictOption } from '@/shared/types'

// ---------- 告警级别 ----------
export const ALARM_LEVEL_MAP: Record<string, DictOption> = {
  URGENT: { label: '紧急', value: 'URGENT', color: '#ef4444' },
  IMPORTANT: { label: '重要', value: 'IMPORTANT', color: '#f59e0b' },
  NORMAL: { label: '一般', value: 'NORMAL', color: '#eab308' },
}

export const ALARM_LEVEL_OPTIONS: DictOption[] = Object.values(ALARM_LEVEL_MAP)

// ---------- 告警类型 ----------
export const ALARM_TYPE_MAP: Record<string, DictOption> = {
  HIGH_WATER: { label: '高水位', value: 'HIGH_WATER', color: '#ef4444' },
  LOW_WATER: { label: '低水位', value: 'LOW_WATER', color: '#3b82f6' },
  FLOW_SPIKE: { label: '流量突变', value: 'FLOW_SPIKE', color: '#f59e0b' },
  POWER: { label: '功率异常', value: 'POWER', color: '#6366f1' },
  DEVICE_OFFLINE: { label: '设备离线', value: 'DEVICE_OFFLINE', color: '#8b5cf6' },
  EXEC_TIMEOUT: { label: '执行超时', value: 'EXEC_TIMEOUT', color: '#f97316' },
  EXEC_FAIL: { label: '执行失败', value: 'EXEC_FAIL', color: '#dc2626' },
  MODEL_HEALTH_DEGRADED: {
    label: '模型健康降级',
    value: 'MODEL_HEALTH_DEGRADED',
    color: '#7c3aed',
  },
}

export const ALARM_TYPE_OPTIONS: DictOption[] = Object.values(ALARM_TYPE_MAP)

// ---------- 处理状态 ----------
export const ALARM_STATUS_MAP: Record<string, DictOption> = {
  pending: { label: '未处理', value: 'pending', color: '#ef4444' },
  confirmed: { label: '已确认', value: 'confirmed', color: '#f59e0b' },
  handled: { label: '已处置', value: 'handled', color: '#22c55e' },
}

export const ALARM_STATUS_OPTIONS: DictOption[] = Object.values(ALARM_STATUS_MAP)

// ---------- 设备类型 ----------
export const ALARM_DEVICE_TYPE_MAP: Record<string, DictOption> = {
  gate: { label: '阀门', value: 'gate' },
  hydro: { label: '水文站', value: 'hydro' },
  sensor: { label: '传感器', value: 'sensor' },
}

export const ALARM_DEVICE_TYPE_OPTIONS: DictOption[] = Object.values(ALARM_DEVICE_TYPE_MAP)

// ---------- 告警列表操作按钮定义 ----------
export interface AlarmAction {
  label: string
  type: 'confirm' | 'handle' | 'detail'
  disabled?: boolean
}

/**
 * 根据告警状态返回可用操作
 * 规则：未处理→[确认]；已确认→[处置]；已处置→[查看详情]
 */
export function getAlarmActions(status: string): AlarmAction[] {
  switch (status) {
    case 'pending':
      return [{ label: '确认', type: 'confirm' }]
    case 'confirmed':
      return [{ label: '处置', type: 'handle' }]
    case 'handled':
      return [{ label: '查看详情', type: 'detail' }]
    default:
      return []
  }
}

// ---------- 时间范围快捷项 ----------
export interface TimeRangeOption {
  label: string
  value: string
}

export const TIME_RANGE_OPTIONS: TimeRangeOption[] = [
  { label: '今日', value: 'today' },
  { label: '近7天', value: '7days' },
  { label: '近30天', value: '30days' },
  { label: '自定义', value: 'custom' },
]

// ---------- 持续超限判定默认窗口 ----------
export const DEFAULT_EXCEED_WINDOW_SEC = 30
export const EXCEED_WINDOW_MIN = 10
export const EXCEED_WINDOW_MAX = 120

// ---------- 处置备注长度限制 ----------
export const REMARK_MIN_LENGTH = 10
export const REMARK_MAX_LENGTH = 500

// ---------- WebSocket ----------
export const WS_ALARM_CHANNEL = '/ws/alarms'

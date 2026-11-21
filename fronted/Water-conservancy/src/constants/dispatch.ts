// ============================================================
// 调度决策 — 常量字典
// 依据：《水电站阀门智能调度系统-详细需求报告》第三章
// ============================================================
import type { DictOption } from '@/shared/types'

// ---------- 阀门动作来源 ----------
export const ACTION_SOURCE_MAP: Record<string, string> = {
  dqn_auto: 'DQN 自动',
  manual: '手动操作',
  emergency_override: '紧急覆盖',
  physics_corrected: '物理修正',
}

// ---------- 阀门控制模式（监控大屏阀门检测） ----------
export const GATE_CONTROL_MODE_MAP: Record<string, string> = {
  auto: '自动',
  manual: '手动',
  emergency: '紧急',
  'ai-dqn': 'AI 自动',
  ai_dqn: 'AI 自动',
  'AI-DQN': 'AI 自动',
}

/** 阀门控制模式 → 中文 */
export function formatGateControlMode(mode?: string | null): string {
  if (!mode) return '-'
  return GATE_CONTROL_MODE_MAP[mode] ?? GATE_CONTROL_MODE_MAP[mode.toLowerCase()] ?? mode
}

/** 操作来源 → 中文 */
export function formatActionSource(source?: string | null): string {
  if (!source) return '-'
  return ACTION_SOURCE_MAP[source] ?? source
}

// ---------- 运行模式 ----------
export const DISPATCH_MODE_MAP: Record<string, DictOption> = {
  auto: { label: '自动', value: 'auto', color: '#22c55e' },
  manual: { label: '手动', value: 'manual', color: '#f59e0b' },
}

// ---------- 自动执行权限等级 ----------
export const AUTO_LEVEL_MAP: Record<number, { label: string; description: string; color: string }> =
  {
    1: {
      label: 'L1 仅建议',
      description: 'AI 只出建议，不自动下发至 PLC，须人工确认后执行',
      color: '#3b82f6',
    },
    2: {
      label: 'L2 半自动',
      description: '置信度 ≥80% 且开度变化 ≤10% 时自动执行，否则降级为仅建议',
      color: '#f59e0b',
    },
    3: {
      label: 'L3 全自动',
      description: 'AI 决策自动执行，异常时告警并等待人工介入',
      color: '#ef4444',
    },
  }

export const AUTO_LEVEL_OPTIONS = Object.entries(AUTO_LEVEL_MAP).map(([value, item]) => ({
  ...item,
  value: Number(value),
}))

/** 指令追踪中视为「执行中」的状态 */
export const IN_PROGRESS_COMMAND_STATUSES = [
  'pending',
  'sent',
  'acknowledged',
  'verified',
] as const

export const PENDING_COMMAND_STORAGE_KEY = 'dispatch_pending_command'

/** 节点控制手动开度（含待提交目标）浏览器持久化，后端暂无逐孔写入接口 */
export const GATE_OPENING_STORAGE_KEY = 'dispatch_gate_openings_v2'

// ---------- 执行结果 ----------
export const DISPATCH_RESULT_MAP: Record<string, DictOption> = {
  success: { label: '成功', value: 'success', color: '#22c55e' },
  fail: { label: '失败', value: 'fail', color: '#ef4444' },
  timeout: { label: '超时', value: 'timeout', color: '#f59e0b' },
  cancelled: { label: '已取消', value: 'cancelled', color: '#6b7280' },
}

// ---------- 置信度颜色分级 ----------
export function getConfidenceColor(confidence: number): string {
  if (confidence >= 80) return '#22c55e'
  if (confidence >= 60) return '#f59e0b'
  return '#ef4444'
}

// ---------- 因素方向图标 ----------
export const FACTOR_DIRECTION_MAP: Record<string, { icon: string; color: string }> = {
  up: { icon: '↑', color: '#ef4444' },
  down: { icon: '↓', color: '#22c55e' },
  neutral: { icon: '—', color: '#6b7280' },
}

// ---------- LSTM 预测配置 ----------
export const LSTM_HORIZONS = [
  { label: '1h', value: '1h' },
  { label: '3h', value: '3h' },
  { label: '6h', value: '6h' },
] as const

export const LSTM_METRICS = [
  { label: '水位', value: 'waterLevel' },
  { label: '流量', value: 'flowRate' },
] as const

// ---------- 手动控制 ----------
export const OPENING_MIN = 0
export const OPENING_MAX = 100
export const OPENING_STEP = 1

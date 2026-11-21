// 阀门互锁 — 后端响应 → 前端展示结构
import type { GateInterlockRule, GateInterlockLog } from '@/types/gateai'
import type { InterlockRule } from '@/stores/gateInterlock'

export interface ApiInterlockRule {
  id: number
  reservoir_id: number | null
  rule_code: string
  rule_name: string
  description: string
  enabled: boolean
  priority: number
  trigger_conditions?: unknown
  constraint_action?: unknown
  created_at?: string
  updated_at?: string
}

export interface ApiInterlockLog {
  id: number
  reservoir_id: number
  rule_id?: number
  decision_id?: number | null
  trigger_time: string
  gate1_opening_before?: string | number
  gate2_opening_before?: string | number
  gate3_opening_before?: string | number
  gate1_opening_after?: string | number
  gate2_opening_after?: string | number
  gate3_opening_after?: string | number
  upstream_level?: string | number
  downstream_level?: string | number
  inflow_rate?: string | number
  action_detail?: { reason?: string; triggered_rule?: string }
  rule?: Pick<ApiInterlockRule, 'rule_code' | 'rule_name'>
  reservoir?: { id?: number; name?: string }
}

function toNum(value: unknown): number {
  const n = Number(value)
  return Number.isFinite(n) ? n : 0
}

/** 后端 0~1 小数 → 前端展示用百分比 */
function openingToPercent(value: unknown): number {
  const n = toNum(value)
  if (n > 0 && n <= 1) return Math.round(n * 1000) / 10
  return n
}

function parseJsonField(value: unknown): Record<string, unknown> {
  if (value == null) return {}
  if (typeof value === 'string') {
    try {
      return JSON.parse(value) as Record<string, unknown>
    } catch {
      return {}
    }
  }
  if (typeof value === 'object') return value as Record<string, unknown>
  return {}
}

function formatRatio(value: unknown): string {
  if (typeof value === 'number' && value > 0 && value <= 1) {
    return `${Math.round(value * 100)}%`
  }
  if (typeof value === 'boolean') return value ? '是' : '否'
  return String(value)
}

const TRIGGER_KEY_LABEL: Record<string, string> = {
  spillway_opening_gt: '溢洪道开度 >',
  two_gates_increase_gt: '任两阀同增 >',
  opening_diff_gt: '开度差 >',
  total_opening_lt: '总开度 <',
}

const ACTION_TYPE_LABEL: Record<string, string> = {
  clamp: '限制',
  freeze: '冻结',
}

const ACTION_KEY_LABEL: Record<string, string> = {
  intake_max: '发电阀上限',
  max_diff: '最大开度差',
  min_total: '最小总开度',
  third_gate_lock: '第三阀锁定',
}

export function formatInterlockTriggerLabel(conditions: unknown): string {
  const obj = parseJsonField(conditions)
  const parts = Object.entries(obj).map(([key, value]) => {
    const prefix = TRIGGER_KEY_LABEL[key] ?? key
    return `${prefix}${formatRatio(value)}`
  })
  return parts.join('；') || '—'
}

export function formatInterlockActionLabel(action: unknown): string {
  const obj = parseJsonField(action)
  const parts: string[] = []
  const actionType = obj.action
  if (typeof actionType === 'string') {
    parts.push(ACTION_TYPE_LABEL[actionType] ?? actionType)
  }
  for (const [key, value] of Object.entries(obj)) {
    if (key === 'action') continue
    if (key === 'third_gate_lock') {
      if (value) parts.push('第三阀锁定')
      continue
    }
    const label = ACTION_KEY_LABEL[key] ?? key
    parts.push(`${label} ${formatRatio(value)}`)
  }
  return parts.join(' · ') || '—'
}

export function toGateInterlockRule(raw: ApiInterlockRule): GateInterlockRule {
  return {
    id: raw.id,
    reservoir_id: raw.reservoir_id,
    rule_code: raw.rule_code,
    rule_name: raw.rule_name,
    description: raw.description,
    enabled: raw.enabled,
    priority: raw.priority,
    trigger_label: formatInterlockTriggerLabel(raw.trigger_conditions),
    action_label: formatInterlockActionLabel(raw.constraint_action),
    trigger_count_7d: 0,
    trigger_conditions: parseJsonField(raw.trigger_conditions),
    constraint_action: parseJsonField(raw.constraint_action),
  }
}

/** PUT /api/v1/settings/gate-interlock/rules/{id} 请求体 */
export function toInterlockUpdatePayload(
  patch: Partial<GateInterlockRule>,
): Record<string, unknown> {
  const payload: Record<string, unknown> = {}
  if (patch.rule_code !== undefined) payload.rule_code = patch.rule_code
  if (patch.rule_name !== undefined) payload.rule_name = patch.rule_name
  if (patch.description !== undefined) payload.description = patch.description
  if (patch.enabled !== undefined) payload.enabled = patch.enabled
  if (patch.priority !== undefined) payload.priority = patch.priority
  if (patch.reservoir_id !== undefined) payload.reservoir_id = patch.reservoir_id
  if (patch.trigger_conditions && Object.keys(patch.trigger_conditions).length) {
    payload.trigger_conditions = patch.trigger_conditions
  }
  if (patch.constraint_action && Object.keys(patch.constraint_action).length) {
    payload.constraint_action = patch.constraint_action
  }
  return payload
}

export function toInterlockRule(raw: ApiInterlockRule): InterlockRule {
  const triggerObj = parseJsonField(raw.trigger_conditions)
  const actionObj = parseJsonField(raw.constraint_action)
  return {
    id: raw.id,
    code: raw.rule_code,
    name: raw.rule_name,
    description: raw.description,
    scope: raw.reservoir_id == null ? 'global' : 'reservoir',
    reservoir_id: raw.reservoir_id,
    priority: raw.priority,
    trigger_conditions: Object.entries(triggerObj).map(([field, threshold]) => ({
      field,
      operator: field.endsWith('_lt') ? 'lt' : 'gt',
      threshold: typeof threshold === 'number' ? threshold : 0,
    })),
    constraint_actions: Object.entries(actionObj)
      .filter(([key]) => key !== 'action')
      .map(([field, threshold]) => ({
        field,
        type: String(actionObj.action ?? 'clamp'),
        threshold: typeof threshold === 'number' ? threshold : 0,
      })),
    is_enabled: raw.enabled,
    trigger_count_7d: 0,
    updated_at: raw.updated_at ?? raw.created_at ?? '',
  }
}

export function normalizeInterlockRules(data: unknown): ApiInterlockRule[] {
  if (!Array.isArray(data)) return []
  return data as ApiInterlockRule[]
}

export function normalizeInterlockLogs(data: unknown): ApiInterlockLog[] {
  if (!Array.isArray(data)) return []
  return data as ApiInterlockLog[]
}

export function toGateInterlockLog(raw: ApiInterlockLog): GateInterlockLog {
  return {
    id: raw.id,
    trigger_time: raw.trigger_time,
    reservoir_id: raw.reservoir_id,
    reservoir_name: raw.reservoir?.name ?? `水库#${raw.reservoir_id}`,
    rule_name: raw.rule?.rule_name ?? raw.action_detail?.triggered_rule ?? '—',
    rule_code: raw.rule?.rule_code ?? raw.action_detail?.triggered_rule,
    decision_id: raw.decision_id ?? undefined,
    upstream_level: toNum(raw.upstream_level),
    downstream_level: toNum(raw.downstream_level),
    inflow_rate: raw.inflow_rate != null ? toNum(raw.inflow_rate) : undefined,
    openings_before: [
      openingToPercent(raw.gate1_opening_before),
      openingToPercent(raw.gate2_opening_before),
      openingToPercent(raw.gate3_opening_before),
    ],
    openings_after: [
      openingToPercent(raw.gate1_opening_after),
      openingToPercent(raw.gate2_opening_after),
      openingToPercent(raw.gate3_opening_after),
    ],
    reason: raw.action_detail?.reason ?? '',
  }
}

export interface ApiInterlockStat {
  rule_id: number
  trigger_count: number
  last_triggered?: string
  rule?: { id?: number; rule_code?: string; rule_name?: string }
}

export function normalizeInterlockStats(data: unknown): ApiInterlockStat[] {
  if (!Array.isArray(data)) return []
  return data as ApiInterlockStat[]
}

export function interlockStatsCountMap(stats: ApiInterlockStat[]): Map<number, number> {
  return new Map(stats.map((s) => [s.rule_id, s.trigger_count]))
}

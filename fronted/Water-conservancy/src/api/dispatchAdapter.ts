// ============================================================
// 调度 API — 后端字段 ↔ 前端类型映射
// ============================================================
import type {
  DecisionDetail,
  DecisionFactor,
  DecisionMode,
  DispatchPlan,
  DispatchRecord,
  GateAction,
  CommandTrace,
  EmergencyStopLog,
  PhysicsValidation,
  PredictionData,
  PredictionPoint,
  WeightsUsed,
} from '@/types/dispatch'

export interface BackendPredictionItem {
  id: number
  equipment_id?: number
  predict_term: 1 | 2 | 3
  base_time: string
  water_seq_json: number[]
  flow_seq_json: number[]
  predict_accuracy: number | string
  created_at: string
}

export interface BackendDecisionItem {
  id: number
  trace_id: string
  reservoir_id: number
  edge_node_id?: number
  prediction_id?: number
  decision_time: string
  decision_mode: string
  risk_rank: number
  upstream_level: number | string
  downstream_level: number | string
  inflow_rate: number | string
  current_opening: number | string
  lstm_predictions?: Record<string, { level: number | string; flow: number | string }>
  recommended_opening: number | string
  confidence: number | string
  factors?: DecisionFactor[]
  alternatives?: DispatchPlan[]
  weights_used?: WeightsUsed | { power?: number; safety?: number; ecology?: number }
  reward_score?: number | string | null
  physics_validation?: Partial<PhysicsValidation> | null
  execution_status: DecisionDetail['execution_status']
  executed_opening?: number | string | null
  executed_at?: string | null
  confirmed_by?: number | null
  reject_reason?: string | null
  actual_level_after?: number | string | null
  actual_power_after?: number | string | null
  created_at: string
  updated_at?: string
  reservoir?: Record<string, unknown>
}

function parseJsonField<T>(val: unknown, fallback: T): T {
  if (val == null || val === '') return fallback
  if (typeof val === 'string') {
    try {
      return JSON.parse(val) as T
    } catch {
      return fallback
    }
  }
  return val as T
}

function parseLstmPredictions(
  val: unknown,
): Record<string, { level: number | string; flow: number | string }> {
  const parsed = parseJsonField<unknown>(val, null)
  if (!parsed) return {}
  if (Array.isArray(parsed)) {
    const level = parsed[0] ?? 0
    return { '1h': { level, flow: 0 } }
  }
  if (typeof parsed === 'object') {
    return parsed as Record<string, { level: number | string; flow: number | string }>
  }
  return {}
}

function parseFactorList(val: unknown): DecisionFactor[] | undefined {
  const parsed = parseJsonField<unknown>(val, null)
  if (!Array.isArray(parsed) || !parsed.length) return undefined
  // 仅有名称无数值时交给 buildDefaultFactors 用实时量补齐
  if (typeof parsed[0] === 'string') return undefined
  if (typeof parsed[0] === 'object') {
    const list = parsed as DecisionFactor[]
    const usable = list.filter(
      (f) => f && f.value != null && f.value !== '' && f.value !== '—',
    )
    return usable.length ? usable : undefined
  }
  return undefined
}

function normalizeBackendDecision(raw: BackendDecisionItem): BackendDecisionItem {
  const factors = parseFactorList(raw.factors)
  const alternatives = parseJsonField<DispatchPlan[]>(raw.alternatives, [])
  const weights = parseJsonField<BackendDecisionItem['weights_used']>(raw.weights_used, raw.weights_used)
  const physics = parseJsonField<Partial<PhysicsValidation> | null>(raw.physics_validation, null)
  return {
    ...raw,
    lstm_predictions: parseLstmPredictions(raw.lstm_predictions),
    factors: factors ?? raw.factors,
    alternatives: Array.isArray(alternatives) ? alternatives : [],
    weights_used: weights ?? undefined,
    physics_validation: physics,
  }
}

function parseBaseTime(s: string): Date {
  return new Date(s.includes('T') ? s : s.replace(' ', 'T'))
}

function termHours(term: 1 | 2 | 3): number {
  return term === 1 ? 1 : term === 2 ? 3 : 6
}

function toNum(v: number | string | null | undefined, fallback = 0): number {
  if (v == null || v === '') return fallback
  const n = typeof v === 'string' ? parseFloat(v) : v
  return Number.isFinite(n) ? n : fallback
}

function toNullableNum(v: number | string | null | undefined): number | null {
  if (v == null || v === '') return null
  const n = typeof v === 'string' ? parseFloat(v) : v
  return Number.isFinite(n) ? n : null
}

function parseDecisionMode(mode: string): DecisionMode {
  if (mode.startsWith('L1')) return 'L1'
  if (mode.startsWith('L2')) return 'L2'
  if (mode.startsWith('L3')) return 'L3'
  if (mode === 'L1' || mode === 'L2' || mode === 'L3') return mode
  return 'L2'
}

function parseWeights(
  raw?: WeightsUsed | { power?: number; safety?: number; ecology?: number },
): WeightsUsed {
  if (!raw) return { power_weight: 0.33, safety_weight: 0.34, ecology_weight: 0.33 }
  if ('power_weight' in raw) return raw
  return {
    power_weight: raw.power ?? 0.33,
    safety_weight: raw.safety ?? 0.34,
    ecology_weight: raw.ecology ?? 0.2,
  }
}

function inferDecisionLevel(mode: string): PhysicsValidation['decision_level'] {
  if (mode.includes('MANUAL') || mode.startsWith('L1')) return 'L1_MANUAL'
  if (mode.includes('SUGGEST') || mode.startsWith('L2')) return 'L2_SUGGEST'
  if (mode.startsWith('L3')) return 'L3_AUTO'
  return 'L2_SUGGEST'
}

function mapPhysicsValidation(
  raw?: Partial<PhysicsValidation> | null,
  decisionMode?: string,
): PhysicsValidation | null {
  if (!raw) return null
  const riskProb = toNum(raw.risk_probability, 0)
  return {
    passed: raw.passed ?? true,
    physics_violation_m: toNum(raw.physics_violation_m, 0),
    physics_correction_steps: raw.physics_correction_steps ?? 0,
    trend_direction: raw.trend_direction ?? 'match',
    risk_level: raw.risk_level ?? 'safe',
    risk_probability: riskProb,
    shadow_levels: raw.shadow_levels ?? [],
    command_smoothed: raw.command_smoothed ?? false,
    smooth_reason: raw.smooth_reason ?? '',
    safety_overridden: raw.safety_overridden ?? false,
    safety_override_reason: raw.safety_override_reason ?? '',
    decision_level:
      raw.decision_level ??
      (decisionMode ? inferDecisionLevel(decisionMode) : 'L2_SUGGEST'),
    gate_limit_touched: raw.gate_limit_touched ?? false,
    rate_exceeded: raw.rate_exceeded ?? false,
    interlock: raw.interlock ?? { triggered: false, rules: [], reason: '' },
    contribution: raw.contribution ?? {
      prediction: 0.85,
      decision: 0.85,
      compliance: 0.85,
      overall: 0.85,
    },
  }
}

function buildDefaultFactors(raw: BackendDecisionItem): DecisionFactor[] {
  const list = Array.isArray(raw.factors) ? raw.factors : []
  const usable = list.filter(
    (f) => f && typeof f === 'object' && f.value != null && f.value !== '' && f.value !== '—',
  )
  if (usable.length) return usable

  const upstream = toNum(raw.upstream_level)
  const inflow = toNum(raw.inflow_rate)
  const opening = toNum(raw.current_opening)
  const recommended = toNum(raw.recommended_opening)
  const lstm1h = raw.lstm_predictions?.['1h']
  const openDir: DecisionFactor['direction'] =
    recommended > opening ? 'up' : recommended < opening ? 'down' : 'neutral'
  return [
    { name: '上游水位', value: `${upstream.toFixed(2)} m`, direction: 'neutral', weight: 0.25 },
    { name: '入库流量', value: `${inflow.toFixed(1)} m³/s`, direction: 'neutral', weight: 0.25 },
    { name: '当前开度', value: `${opening.toFixed(1)}%`, direction: openDir, weight: 0.2 },
    {
      name: 'LSTM 1h 预测',
      value: lstm1h
        ? `${toNum(lstm1h.level).toFixed(2)} m / ${toNum(lstm1h.flow).toFixed(1)} m³/s`
        : '—',
      direction: 'neutral',
      weight: 0.3,
    },
  ]
}

function buildDefaultAlternatives(raw: BackendDecisionItem): DispatchPlan[] {
  if (Array.isArray(raw.alternatives) && raw.alternatives.length) return raw.alternatives
  const opening = toNum(raw.recommended_opening)
  const confidence = toNum(raw.confidence)
  return [
    {
      id: '推荐',
      opening,
      expectedLevel: toNum(raw.upstream_level),
      power: 0,
      safetyScore: raw.physics_validation?.passed === false ? 70 : 92,
      totalScore: confidence,
      recommended: true,
      confidence,
    },
  ]
}

function jsonToSeq(
  baseTime: string,
  values: number[],
  predictTerm: 1 | 2 | 3,
): PredictionPoint[] {
  if (!values.length) return []
  const base = parseBaseTime(baseTime)
  const stepMs = (termHours(predictTerm) * 3600000) / values.length
  const anchor: PredictionPoint = {
    time: base.toISOString(),
    value: values[0],
  }
  const future = values.map((value, i) => ({
    time: new Date(base.getTime() + (i + 1) * stepMs).toISOString(),
    value,
  }))
  return [anchor, ...future]
}

export function mapBackendPrediction(raw: BackendPredictionItem): PredictionData {
  const term = raw.predict_term
  const accuracy =
    typeof raw.predict_accuracy === 'string'
      ? parseFloat(raw.predict_accuracy)
      : raw.predict_accuracy
  return {
    id: raw.id,
    base_time: raw.base_time,
    predict_term: term,
    water_seq: jsonToSeq(raw.base_time, raw.water_seq_json ?? [], term),
    flow_seq: jsonToSeq(raw.base_time, raw.flow_seq_json ?? [], term),
    predict_accuracy: Number.isFinite(accuracy) ? accuracy : 0,
    created_at: raw.created_at,
  }
}

export function mapBackendDecision(raw: BackendDecisionItem): DecisionDetail {
  const item = normalizeBackendDecision(raw)
  const lstm = item.lstm_predictions ?? {}
  const lstmMapped = Object.fromEntries(
    Object.entries(lstm).map(([k, v]) => [
      k,
      { level: toNum(v.level), flow: toNum(v.flow) },
    ]),
  )
  return {
    id: item.id,
    trace_id: item.trace_id,
    reservoir_id: item.reservoir_id,
    decision_time: item.decision_time,
    decision_mode: parseDecisionMode(item.decision_mode),
    risk_rank: Math.min(3, Math.max(1, item.risk_rank)) as 1 | 2 | 3,
    upstream_level: toNum(item.upstream_level),
    downstream_level: toNum(item.downstream_level),
    inflow_rate: toNum(item.inflow_rate),
    current_opening: toNum(item.current_opening),
    lstm_predictions: lstmMapped,
    recommended_opening: toNum(item.recommended_opening),
    confidence: toNum(item.confidence),
    factors: buildDefaultFactors(item),
    alternatives: buildDefaultAlternatives(item),
    weights_used: parseWeights(item.weights_used),
    reward_score: toNum(item.reward_score, 0),
    physics_validation: mapPhysicsValidation(item.physics_validation, item.decision_mode),
    execution_status: item.execution_status,
    executed_opening: toNullableNum(item.executed_opening),
    actual_level_after: toNullableNum(item.actual_level_after),
    actual_power_after: toNullableNum(item.actual_power_after),
    created_at: item.created_at,
  }
}

function deriveAction(raw: BackendDecisionItem): string {
  const item = normalizeBackendDecision(raw)
  if (item.execution_status === 'executed') {
    return item.decision_mode.includes('MANUAL') ? '人工下发' : '自动执行'
  }
  if (item.execution_status === 'pending') return '待确认'
  if (item.execution_status === 'rejected') return '已驳回'
  if (item.execution_status === 'failed') return '执行失败'
  return item.decision_mode
}

function deriveOperator(raw: BackendDecisionItem): string | undefined {
  const item = normalizeBackendDecision(raw)
  if (item.confirmed_by) return `用户 #${item.confirmed_by}`
  if (item.decision_mode.includes('AUTO')) return '系统'
  if (item.decision_mode.includes('MANUAL')) return '待人工'
  return '—'
}

function formatDecisionModeLabel(mode: string): string {
  if (mode.startsWith('L1') || mode.includes('MANUAL')) return 'L1'
  if (mode.startsWith('L2') || mode.includes('SUGGEST')) return 'L2'
  if (mode.startsWith('L3') || mode.includes('AUTO')) return 'L3'
  return mode
}

/** 优先取待执行决策，否则取列表首条（假定按时间倒序） */
export function pickDecisionId(list: BackendDecisionItem[]): number | null {
  if (!list.length) return null
  const pending = list.find((d) => d.execution_status === 'pending')
  return pending?.id ?? list[0]?.id ?? null
}

export function mapBackendDispatchRecord(raw: BackendDecisionItem): DispatchRecord {
  const item = normalizeBackendDecision(raw)
  const mapped = mapBackendDecision(item)
  return {
    id: mapped.id,
    decision_time: mapped.decision_time,
    decision_mode: mapped.decision_mode,
    decision_mode_label: formatDecisionModeLabel(item.decision_mode),
    recommended_opening: mapped.recommended_opening,
    confidence: mapped.confidence,
    risk_rank: mapped.risk_rank,
    execution_status: mapped.execution_status,
    physics_validation: mapped.physics_validation,
    action: deriveAction(item),
    operator_name: deriveOperator(item),
    snapshot: {
      factors: mapped.factors.slice(0, 5),
      confidence: mapped.confidence,
      recommended_opening: mapped.recommended_opening,
      plans: mapped.alternatives.map((p) => ({
        id: p.id,
        opening: p.opening,
        totalScore: p.totalScore,
        recommended: p.recommended,
      })),
    },
  }
}

export interface BackendGateActionItem {
  id: number
  equipment_id: number
  decision_id?: number
  command_id?: string | null
  interlock_rule_id?: number | null
  previous_opening: number | string
  target_opening: number | string
  actual_opening: number | string | null
  action_type: string
  action_source: string
  duration_ms: number
  is_smoothed: boolean | number
  smooth_reason?: string | null
  acted_at: string
  created_at?: string
  interlock_rule?: { id?: number; name?: string; rule_name?: string } | null
}

export function mapBackendGateAction(raw: BackendGateActionItem): GateAction {
  const rule = raw.interlock_rule
  return {
    id: raw.id,
    equipment_id: raw.equipment_id,
    previous_opening: toNum(raw.previous_opening),
    target_opening: toNum(raw.target_opening),
    actual_opening: toNullableNum(raw.actual_opening),
    action_type: raw.action_type as GateAction['action_type'],
    action_source: raw.action_source as GateAction['action_source'],
    duration_ms: raw.duration_ms,
    is_smoothed:
      typeof raw.is_smoothed === 'boolean' ? (raw.is_smoothed ? 1 : 0) : toNum(raw.is_smoothed),
    acted_at: raw.acted_at,
    interlock_rule_id: raw.interlock_rule_id ?? rule?.id ?? null,
    interlock_rule_name: rule?.name ?? rule?.rule_name ?? null,
  }
}

export interface BackendCommandTraceItem {
  id: number
  command_id: string
  trace_id: string
  decision_id?: number | null
  gate_action_id?: number | null
  edge_node_id: number
  operator_id?: number | null
  command_type: string
  payload?: string | Record<string, unknown>
  target_equipment?: number | null
  target_opening?: number | string | null
  status: string
  sent_at?: string | null
  acknowledged_at?: string | null
  verified_at?: string | null
  executed_at?: string | null
  feedback_at?: string | null
  full_delay_ms?: number | null
  execution_result?: Record<string, unknown> | null
  reject_reason?: string | null
  is_emergency?: boolean | number
  created_at: string
  updated_at?: string
}

function parsePayload(raw?: string | Record<string, unknown>): Record<string, unknown> {
  if (!raw) return {}
  if (typeof raw === 'object') return raw
  try {
    return JSON.parse(raw) as Record<string, unknown>
  } catch {
    return { raw }
  }
}

export function mapBackendCommandTrace(raw: BackendCommandTraceItem): CommandTrace {
  return {
    id: raw.id,
    command_id: raw.command_id,
    trace_id: raw.trace_id,
    decision_id: raw.decision_id ?? null,
    gate_action_id: raw.gate_action_id ?? null,
    edge_node_id: raw.edge_node_id,
    operator_id: raw.operator_id ?? null,
    command_type: raw.command_type,
    payload: parsePayload(raw.payload),
    target_equipment: raw.target_equipment ?? null,
    target_opening: toNum(raw.target_opening),
    status: raw.status as CommandTrace['status'],
    sent_at: raw.sent_at ?? null,
    acknowledged_at: raw.acknowledged_at ?? null,
    verified_at: raw.verified_at ?? null,
    executed_at: raw.executed_at ?? null,
    feedback_at: raw.feedback_at ?? null,
    full_delay_ms: raw.full_delay_ms ?? null,
    execution_result: raw.execution_result ?? null,
    reject_reason: raw.reject_reason ?? null,
    is_emergency: raw.is_emergency === true || raw.is_emergency === 1,
    created_at: raw.created_at,
    updated_at: raw.updated_at ?? raw.created_at,
  }
}

export interface BackendEmergencyStopItem {
  id: number
  trigger_user_id: number
  decision_id?: number | null
  command_id?: string | number | null
  trigger_time: string
  edge_ack_time?: string | null
  plc_shut_time?: string | null
  recover_user_id?: number | null
  recover_time?: string | null
  stop_reason: string
  created_at?: string | null
  decision?: unknown
}

export function mapBackendEmergencyStopLog(raw: BackendEmergencyStopItem): EmergencyStopLog {
  return {
    id: raw.id,
    trigger_user_id: raw.trigger_user_id,
    trigger_time: raw.trigger_time,
    edge_ack_time: raw.edge_ack_time ?? null,
    plc_shut_time: raw.plc_shut_time ?? null,
    recover_time: raw.recover_time ?? null,
    stop_reason: raw.stop_reason,
    command_id: raw.command_id ?? null,
    recover_user_id: raw.recover_user_id ?? null,
    decision_id: raw.decision_id ?? null,
  }
}

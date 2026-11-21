// GateAI 调度模块 — 真实 API + Mock 降级（VITE_GATEAI_MOCK_FALLBACK 控制）
// 开发环境默认开启：后端 401/404 时自动用本地 Mock，避免设置页白屏
export const GATEAI_MOCK_FALLBACK =
  import.meta.env.VITE_GATEAI_MOCK_FALLBACK === 'true' ||
  (import.meta.env.VITE_GATEAI_MOCK_FALLBACK !== 'false' && import.meta.env.DEV)

import type {
  ModelMetricLatest,
  ModelMetricHistoryPoint,
  ModelMetricDetailRow,
  PhysicsGuardConfig,
  GateInterlockRule,
  GateInterlockLog,
  GateInterlockLogApiItem,
  ModelHealthOverviewItem,
  ModelVersionOption,
  AIHealthOverviewResponse,
} from '@/types/gateai'

import { isApiNotFoundError } from '@/utils/apiError'
import { RESERVOIR_OPTIONS } from '@/constants/settings'

import { gateaiSharedStore } from './gateaiSharedStore'
import type { ApiResponse } from '@/shared/types'

/** v1 路径前缀，与 settings.ts 保持一致 */
const V1 = import.meta.env.VITE_API_V1_PREFIX ?? '/v1'

// ── 真实 API 导入 ──
import {
  getPhysicsGuard,
  updatePhysicsGuard,
  getPhysicsGuardHistory,
  rollbackPhysicsGuard as rollbackPhysicsGuardApi,
  clonePhysicsGuard as clonePhysicsGuardApi,
  getAIMetrics,
  getAIMetricsHistory,
  getAIMetricsDetail,
  getAIHealthOverview,
  getAIVersionCompare,
  getInterlockRules,
  updateInterlockRule as updateInterlockRuleApi,
  toggleInterlockRule as toggleInterlockRuleApi,
  getInterlockLogs,
  getInterlockStats,
  type AIMetricsHistoryItem,
} from './settings'
import http from './request'
import {
  normalizeInterlockRules,
  normalizeInterlockStats,
  interlockStatsCountMap,
  toGateInterlockRule,
  toInterlockUpdatePayload,
} from './interlockAdapter'
import type { BackendPhysicsGuardRaw } from './physicsGuardAdapter'
import {
  mapBackendPhysicsGuardConfig,
  mapBackendPhysicsGuardHistoryGateai,
  mapBackendPhysicsGuardSummary,
  toBackendPhysicsGuardPayload,
} from './physicsGuardAdapter'

const METRICS: Record<number, ModelMetricLatest> = {
  1: {
    overall_score: 0.82,
    health_grade: 'A',

    water_level_mae_24h: 0.042,
    safety_override_rate: 0.08,
    l3_auto_rate: 0.71,

    prediction_score: 0.85,
    decision_score: 0.8,
    compliance_score: 0.81,

    metric_time: new Date().toISOString(),
    dimension_weights: { prediction: 0.4, decision: 0.35, compliance: 0.25, safety_coverage: 0.15, decision_auto_rate: 0.15 },
  },

  2: {
    overall_score: 0.76,
    health_grade: 'B',

    water_level_mae_24h: 0.055,
    safety_override_rate: 0.12,
    l3_auto_rate: 0.65,

    prediction_score: 0.78,
    decision_score: 0.74,
    compliance_score: 0.75,

    metric_time: new Date().toISOString(),
    dimension_weights: { prediction: 0.4, decision: 0.35, compliance: 0.25, safety_coverage: 0.15, decision_auto_rate: 0.15 },
  },

  3: {
    overall_score: 0.88,
    health_grade: 'A',

    water_level_mae_24h: 0.038,
    safety_override_rate: 0.06,
    l3_auto_rate: 0.74,

    prediction_score: 0.87,
    decision_score: 0.82,
    compliance_score: 0.84,

    metric_time: new Date().toISOString(),
    dimension_weights: { prediction: 0.4, decision: 0.35, compliance: 0.25, safety_coverage: 0.15, decision_auto_rate: 0.15 },
  },

  4: {
    overall_score: 0.65,
    health_grade: 'B',

    water_level_mae_24h: 0.062,
    safety_override_rate: 0.15,
    l3_auto_rate: 0.58,

    prediction_score: 0.7,
    decision_score: 0.66,
    compliance_score: 0.68,

    metric_time: new Date().toISOString(),
    dimension_weights: { prediction: 0.4, decision: 0.35, compliance: 0.25, safety_coverage: 0.15, decision_auto_rate: 0.15 },
  },
}

const VERSION_CATALOG: Record<number, ModelVersionOption[]> = {
  1: [
    { id: 1, version: 'Physics-LSTM v5.1', source: '边缘实时', scores: {} },
    { id: 2, version: 'Physics-LSTM v5.0', source: '数字孪生仿真', scores: {} },
    { id: 3, version: 'Physics-LSTM v4.9', source: '历史归档', scores: {} },
  ],
}

function versionScores(reservoirId: number, version: string): Record<string, number> {
  const m = METRICS[reservoirId] ?? METRICS[1]

  const base = version.includes('5.1') ? 1 : version.includes('5.0') ? 0.96 : 0.9

  return {
    预测准确性: +(m.prediction_score * base).toFixed(2),

    决策可靠性: +(m.decision_score * base).toFixed(2),

    物理合规性: +(m.compliance_score * base).toFixed(2),

    安全覆盖率: +((1 - m.safety_override_rate) * base).toFixed(2),

    决策自主率: +(m.l3_auto_rate * base).toFixed(2),
  }
}

function historyFor(reservoirId: number, hours = 24): ModelMetricHistoryPoint[] {
  const base = METRICS[reservoirId]?.overall_score ?? 0.75
  const count = Math.min(hours, 48)

  return Array.from({ length: count }, (_, i) => {
    const d = new Date()
    d.setHours(d.getHours() - (count - 1 - i))

    const jitter = (i - Math.floor(count / 2)) * 0.005

    const point: ModelMetricHistoryPoint = {
      time: d.toISOString().slice(0, 16),

      prediction_score: +(base + 0.03 + jitter).toFixed(2),

      decision_score: +(base - 0.02 + jitter).toFixed(2),

      compliance_score: +(base + jitter * 0.5).toFixed(2),

      overall_score: +(base + jitter * 0.8).toFixed(2),
    }

    const mid = Math.floor(count / 2)
    if (i === mid) point.grade_event = { from: 'B', to: 'A', label: '综合评分回升至 A 级' }
    if (i === mid - 4) point.grade_event = { from: 'A', to: 'B', label: '安全覆盖率下降，降至 B 级' }

    return point
  })
}

function detailRows(reservoirId: number, hours = 24): ModelMetricDetailRow[] {
  const m = METRICS[reservoirId] ?? METRICS[1]

  const count = Math.min(Math.max(hours, 1), 48)

  return Array.from({ length: count }, (_, i) => ({
    metric_time: new Date(Date.now() - i * 3600000).toISOString().replace('T', ' ').slice(0, 19),

    water_level_mae_24h: +(m.water_level_mae_24h + i * 0.002).toFixed(3),

    safety_override_rate: +(m.safety_override_rate + i * 0.005).toFixed(3),

    physics_correction_rate: +(0.05 + i * 0.008).toFixed(3),

    gate_limit_touch_rate: +(0.02 + i * 0.003).toFixed(3),

    overall_score: +(m.overall_score - i * 0.008).toFixed(2),

    health_grade: m.health_grade,
  }))
}

function delay<T>(data: T, ms = 150): Promise<T> {
  return new Promise((r) => setTimeout(() => r(data), ms))
}

type AIMetricsLatestRaw = {
  overall_score?: number
  health_grade?: string
  water_level_mae_24h?: number
  safety_override_rate?: number
  l3_auto_rate?: number
  prediction_score?: number
  decision_score?: number
  compliance_score?: number
  metric_time?: string
  decision_level_dist?: Partial<Record<'L1' | 'L2' | 'L3' | 'OVERRIDE', number>>
  dimension_weights?: Record<string, number>
}

function normalizeModelMetricsLatest(raw: AIMetricsLatestRaw): ModelMetricLatest {
  const dist = raw.decision_level_dist
  return {
    overall_score: raw.overall_score ?? 0,
    health_grade: (raw.health_grade ?? 'C') as ModelMetricLatest['health_grade'],
    water_level_mae_24h: raw.water_level_mae_24h ?? 0,
    safety_override_rate: raw.safety_override_rate ?? 0,
    l3_auto_rate: raw.l3_auto_rate ?? dist?.L3 ?? 0,
    prediction_score: raw.prediction_score ?? 0,
    decision_score: raw.decision_score ?? 0,
    compliance_score: raw.compliance_score ?? 0,
    metric_time: raw.metric_time ?? '',
    dimension_weights: raw.dimension_weights ?? {
      prediction: 0.4,
      decision: 0.35,
      compliance: 0.25,
      safety_coverage: 0.15,
      decision_auto_rate: 0.15,
    },
  }
}

function normalizeDetailRow(raw: Record<string, unknown>): ModelMetricDetailRow {
  return {
    metric_time: String(raw.metric_time ?? raw.time ?? ''),
    water_level_mae_24h: Number(raw.water_level_mae_24h ?? raw.water_level_mae ?? 0),
    safety_override_rate: Number(raw.safety_override_rate ?? 0),
    physics_correction_rate: Number(raw.physics_correction_rate ?? 0),
    gate_limit_touch_rate: Number(raw.gate_limit_touch_rate ?? 0),
    overall_score: Number(raw.overall_score ?? 0),
    health_grade: String(raw.health_grade ?? 'C') as ModelMetricDetailRow['health_grade'],
  }
}

function extractDetailList(data: unknown): ModelMetricDetailRow[] {
  if (Array.isArray(data)) return data.map((item) => normalizeDetailRow(item as Record<string, unknown>))
  if (data && typeof data === 'object') {
    const obj = data as Record<string, unknown>
    for (const key of ['list', 'items', 'records']) {
      if (Array.isArray(obj[key])) {
        return (obj[key] as Record<string, unknown>[]).map((item) => normalizeDetailRow(item))
      }
    }
  }
  return []
}

/** 后端未部署 /settings/ai/* 时跳过重复 404 / 30001 请求 */
const aiApiAvailability = {
  metrics: null as boolean | null,
  history: null as boolean | null,
  health: null as boolean | null,
  /** GET /settings/ai/metrics/list */
  detail: null as boolean | null,
}

function markAiApiUnavailable(key: keyof typeof aiApiAvailability, e: unknown) {
  if (isApiNotFoundError(e)) {
    aiApiAvailability[key] = false
    if (import.meta.env.DEV) {
      console.info(`[API] /settings/ai/${key} 尚未部署，已改用降级数据`)
    }
  }
}

function isAiApiDisabled(key: keyof typeof aiApiAvailability): boolean {
  return aiApiAvailability[key] === false
}

/** @deprecated 与 aiApiAvailability.detail 同步 */
let metricsDetailApiAvailable: boolean | null = null

function getHttpStatus(e: unknown): number | undefined {
  if (e && typeof e === 'object' && 'response' in e) {
    return (e as { response?: { status?: number } }).response?.status
  }
  return undefined
}

async function fetchDetailFromHistory(reservoirId: number, limit: number): Promise<ModelMetricDetailRow[]> {
  if (isAiApiDisabled('history')) return []
  try {
    const res = await getAIMetricsHistory({ reservoir_id: reservoirId, hours: 168 })
    if (res.data?.code === 0) {
      const list = extractHistoryList(res.data.data)
      return list
        .slice(-limit)
        .reverse()
        .map((item) => normalizeDetailRow(item as unknown as Record<string, unknown>))
    }
  } catch {
    /* ignore */
  }
  return []
}

export async function fetchReservoirOptions(): Promise<{ id: number; name: string }[]> {
  try {
    const overview = await fetchModelHealthOverview()
    if (overview.length > 0) {
      return overview.map((r) => ({ id: r.reservoir_id, name: r.reservoir_name }))
    }
  } catch (e) {
    if (!GATEAI_MOCK_FALLBACK) throw e
  }
  return delay(gateaiSharedStore.getReservoirs())
}

// ═══ AI 模型健康度 ═══
export async function fetchModelMetricsLatest(reservoirId: number): Promise<ModelMetricLatest> {
  if (isAiApiDisabled('metrics')) {
    return delay(METRICS[reservoirId] ?? METRICS[1])
  }
  try {
    const res = await getAIMetrics({ reservoir_id: reservoirId })
    if (res.data?.code === 0 && res.data.data) {
      aiApiAvailability.metrics = true
      return normalizeModelMetricsLatest(res.data.data as AIMetricsLatestRaw)
    }
  } catch (e) {
    markAiApiUnavailable('metrics', e)
    if (!GATEAI_MOCK_FALLBACK) throw e
  }
  return delay(METRICS[reservoirId] ?? METRICS[1])
}

function extractHistoryList(data: unknown): AIMetricsHistoryItem[] {
  if (Array.isArray(data)) return data as AIMetricsHistoryItem[]
  if (data && typeof data === 'object') {
    const obj = data as Record<string, unknown>
    for (const key of ['list', 'items', 'records', 'history']) {
      if (Array.isArray(obj[key])) return obj[key] as AIMetricsHistoryItem[]
    }
  }
  return []
}

function normalizeHistoryPoint(raw: AIMetricsHistoryItem | ModelMetricHistoryPoint): ModelMetricHistoryPoint {
  const item = raw as AIMetricsHistoryItem & ModelMetricHistoryPoint
  const time = item.metric_time ?? item.time ?? ''
  return {
    time,
    prediction_score: item.prediction_score,
    decision_score: item.decision_score,
    compliance_score: item.compliance_score,
    overall_score: item.overall_score,
    health_grade: (item.health_grade as ModelMetricHistoryPoint['health_grade']) ?? undefined,
    grade_event: item.grade_event,
  }
}

export async function fetchModelMetricsHistory(
  reservoirId: number,
  hours = 24,
): Promise<ModelMetricHistoryPoint[]> {
  if (isAiApiDisabled('history')) {
    return delay(historyFor(reservoirId, hours))
  }
  try {
    const res = await getAIMetricsHistory({ reservoir_id: reservoirId, hours })
    if (res.data?.code === 0) {
      const list = extractHistoryList(res.data.data)
      aiApiAvailability.history = true
      return list.map((item) => normalizeHistoryPoint(item))
    }
  } catch (e) {
    markAiApiUnavailable('history', e)
    if (!GATEAI_MOCK_FALLBACK) throw e
  }
  return delay(historyFor(reservoirId, hours))
}

export async function fetchModelMetricsDetail(
  reservoirId: number,
  params?: { hours?: number; page?: number; page_size?: number },
): Promise<ModelMetricDetailRow[]> {
  const limit = Math.min(params?.page_size ?? params?.hours ?? 24, 100)

  if (isAiApiDisabled('detail') || metricsDetailApiAvailable === false) {
    return fetchDetailFromHistory(reservoirId, limit)
  }

  try {
    const res = await getAIMetricsDetail({
      reservoir_id: reservoirId,
      page: params?.page,
      page_size: limit,
    })
    if (res.data?.code === 0 && res.data.data) {
      const list = extractDetailList(res.data.data)
      if (list.length > 0) {
        aiApiAvailability.detail = true
        metricsDetailApiAvailable = true
        return list
      }
    }
  } catch (e) {
    if (isApiNotFoundError(e)) {
      aiApiAvailability.detail = false
      metricsDetailApiAvailable = false
      return fetchDetailFromHistory(reservoirId, limit)
    }
    markAiApiUnavailable('detail', e)
    if (!GATEAI_MOCK_FALLBACK) throw e
  }

  const fromHistory = await fetchDetailFromHistory(reservoirId, limit)
  if (fromHistory.length > 0) return fromHistory

  if (GATEAI_MOCK_FALLBACK) return delay(detailRows(reservoirId, limit))
  return []
}

function reservoirNameById(id: number): string {
  return (
    RESERVOIR_OPTIONS.find((r) => r.value === id)?.label
    ?? gateaiSharedStore.getReservoirs().find((r) => r.id === id)?.name
    ?? `水库 #${id}`
  )
}

function normalizeHealthOverview(data: unknown): ModelHealthOverviewItem[] {
  if (Array.isArray(data)) {
    return data.map((item) => {
      const row = item as ModelHealthOverviewItem
      return {
        ...row,
        reservoir_name: row.reservoir_name || reservoirNameById(row.reservoir_id),
      }
    })
  }
  if (data && typeof data === 'object' && Array.isArray((data as AIHealthOverviewResponse).reservoirs)) {
    return (data as AIHealthOverviewResponse).reservoirs.map((item) => ({
      reservoir_id: item.reservoir_id,
      reservoir_name: item.reservoir_name || reservoirNameById(item.reservoir_id),
      overall_score: item.overall_score,
      health_grade: item.health_grade as ModelHealthOverviewItem['health_grade'],
      metric_time: item.metric_time,
    }))
  }
  return []
}

export async function fetchModelHealthOverview(): Promise<ModelHealthOverviewItem[]> {
  if (isAiApiDisabled('health')) {
    const list: ModelHealthOverviewItem[] = gateaiSharedStore.getReservoirs().map((r) => {
      const m = METRICS[r.id] ?? METRICS[1]
      return {
        reservoir_id: r.id,
        reservoir_name: r.name,
        overall_score: m.overall_score,
        health_grade: m.health_grade,
        metric_time: m.metric_time,
      }
    })
    return delay(list)
  }
  try {
    const res = await getAIHealthOverview()
    if (res.data?.code === 0 && res.data.data) {
      const list = normalizeHealthOverview(res.data.data)
      if (list.length > 0) {
        aiApiAvailability.health = true
        return list
      }
    }
  } catch (e) {
    markAiApiUnavailable('health', e)
    if (!GATEAI_MOCK_FALLBACK) throw e
  }
  const list: ModelHealthOverviewItem[] = gateaiSharedStore.getReservoirs().map((r) => {
    const m = METRICS[r.id] ?? METRICS[1]
    return {
      reservoir_id: r.id,
      reservoir_name: r.name,
      overall_score: m.overall_score,
      health_grade: m.health_grade,
      metric_time: m.metric_time,
    }
  })
  return delay(list)
}

export async function fetchModelVersionOptions(_reservoirId: number) {
  // TODO: 后续接 GET /v1/settings/models 获取真实模型列表
  const opts = (VERSION_CATALOG[_reservoirId] ?? VERSION_CATALOG[1]).map((v) => ({
    ...v,
    scores: versionScores(_reservoirId, v.version),
  }))
  return delay(opts)
}

// 后端返回的英文字段 → 雷达图中文维度名
const SCORE_KEY_MAP: Record<string, string> = {
  prediction_score: '预测准确性',
  decision_score: '决策可靠性',
  compliance_score: '物理合规性',
  safety_override_rate: '安全覆盖率',
  l3_auto_rate: '决策自主率',
}

function normalizeCompareScores(raw: Record<string, number> | undefined): Record<string, number> {
  if (!raw || Object.keys(raw).length === 0) return {}
  const out: Record<string, number> = {}
  for (const [en, zh] of Object.entries(SCORE_KEY_MAP)) {
    if (raw[en] != null) out[zh] = raw[en]
  }
  // 如果映射后为空，直接用原始 key（兜底）
  if (Object.keys(out).length === 0) return raw
  return out
}

export async function fetchModelCompare(
  reservoirId: number,
  modelAId?: number,
  modelBId?: number,
) {
  try {
    if (modelAId != null && modelBId != null) {
      const res = await getAIVersionCompare({ reservoir_id: reservoirId, model_a_id: modelAId, model_b_id: modelBId })
      if (res.data?.code === 0 && res.data.data) {
        const d = res.data.data as any
        const scoresA = normalizeCompareScores(d.period_a?.avg)
        const scoresB = normalizeCompareScores(d.period_b?.avg)
        // 两边都没有数据才回退 mock
        if (Object.keys(scoresA).length > 0 || Object.keys(scoresB).length > 0) {
          return {
            current: {
              version: `模型 #${modelAId}`,
              source: d.period_a?.start ? `${d.period_a.start} ~ ${d.period_a.end}` : '',
              scores: scoresA,
            },
            previous: {
              version: `模型 #${modelBId}`,
              source: d.period_b?.start ? `${d.period_b.start} ~ ${d.period_b.end}` : '',
              scores: scoresB,
            },
          }
        }
      }
    }
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  // fallback mock — 按所选模型 ID 匹配，避免切换版本后数据不变
  const opts = VERSION_CATALOG[reservoirId] ?? VERSION_CATALOG[1]
  const curOpt = opts.find((o) => o.id === modelAId) ?? opts[0]
  const prevOpt = opts.find((o) => o.id === modelBId) ?? opts[1] ?? opts[0]
  return delay({
    current: { version: curOpt.version, source: curOpt.source, scores: versionScores(reservoirId, curOpt.version) },
    previous: { version: prevOpt.version, source: prevOpt.source, scores: versionScores(reservoirId, prevOpt.version) },
  })
}

// ═══ 物理防护配置 ═══
export async function fetchPhysicsGuardConfig(reservoirId: number): Promise<PhysicsGuardConfig> {
  try {
    const res = await getPhysicsGuard({ reservoir_id: reservoirId })
    if (res.data?.code === 0 && res.data.data) {
      return mapBackendPhysicsGuardConfig(res.data.data as BackendPhysicsGuardRaw)
    }
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  return delay(gateaiSharedStore.getPhysicsConfig(reservoirId))
}

export async function savePhysicsGuardConfig(
  config: PhysicsGuardConfig,
  meta?: { description?: string; changed_by_name?: string },
): Promise<null> {
  try {
    const id = config.id
    if (id) {
      const payload = toBackendPhysicsGuardPayload(config, meta)
      const res = await updatePhysicsGuard(id, payload, config.reservoir_id)
      if (res.data?.code === 0) return null
    }
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  gateaiSharedStore.savePhysicsConfig(config, meta)
  return delay(null)
}

export async function fetchPhysicsGuardHistory(reservoirId: number) {
  try {
    const res = await getPhysicsGuardHistory({ reservoir_id: reservoirId })
    if (res.data?.code === 0 && Array.isArray(res.data.data)) {
      return mapBackendPhysicsGuardHistoryGateai(res.data.data as BackendPhysicsGuardRaw[])
    }
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  return delay(gateaiSharedStore.getPhysicsHistory(reservoirId))
}

export async function fetchPhysicsGuardHistoryVersions(reservoirId: number) {
  // 后端未提供独立的版本列表接口，复用 history 接口
  return fetchPhysicsGuardHistory(reservoirId)
}

export async function rollbackPhysicsGuard(reservoirId: number, historyId: number) {
  try {
    const res = await rollbackPhysicsGuardApi(historyId)
    if (res.data?.code === 0 && res.data.data) {
      const raw = res.data.data as BackendPhysicsGuardRaw
      return {
        new_version: raw.config_version,
        msg: res.data.msg,
        config: mapBackendPhysicsGuardConfig(raw),
        summary: mapBackendPhysicsGuardSummary(raw),
      }
    }
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  const config = gateaiSharedStore.rollbackPhysics(reservoirId, historyId)
  const version = (config as { config_version?: string }).config_version ?? ''
  return delay({
    new_version: version,
    msg: version ? `已回滚至 v${version}` : '已回滚',
    config,
    summary: gateaiSharedStore.getPhysicsSummary(reservoirId) as import('@/types/dispatch').PhysicsGuardSummary,
  })
}

export async function clonePhysicsGuardConfig(fromId: number, toId: number, _version?: string) {
  try {
    const res = await clonePhysicsGuardApi({ source_reservoir_id: fromId, target_reservoir_id: toId })
    if (res.data?.code === 0 && res.data.data) {
      const raw = res.data.data as BackendPhysicsGuardRaw
      return {
        msg: res.data.msg,
        config: mapBackendPhysicsGuardConfig(raw),
        summary: mapBackendPhysicsGuardSummary(raw),
      }
    }
    throw new Error(res.data?.msg || 'clone failed')
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  const config = gateaiSharedStore.clonePhysics(fromId, toId, _version)
  return delay({
    msg: '配置克隆成功',
    config,
    summary: gateaiSharedStore.getPhysicsSummary(toId) as import('@/types/dispatch').PhysicsGuardSummary,
  })
}

// ═══ 阀门互锁 ═══
export async function fetchInterlockRules(reservoirId: number): Promise<GateInterlockRule[]> {
  try {
    const [rulesRes, statsRes] = await Promise.all([
      getInterlockRules({ reservoir_id: reservoirId }),
      getInterlockStats({ reservoir_id: reservoirId, days: 7 }),
    ])
    if (rulesRes.data?.code === 0 && rulesRes.data.data) {
      const statsMap = statsRes.data?.code === 0 && statsRes.data.data
        ? interlockStatsCountMap(normalizeInterlockStats(statsRes.data.data))
        : new Map<number, number>()
      return normalizeInterlockRules(rulesRes.data.data).map((raw) => {
        const rule = toGateInterlockRule(raw)
        rule.trigger_count_7d = statsMap.get(rule.id) ?? 0
        return rule
      })
    }
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  return delay(gateaiSharedStore.getInterlockRules(reservoirId))
}

export async function toggleInterlockRule(ruleId: number, enabled: boolean): Promise<boolean> {
  try {
    const res = await toggleInterlockRuleApi(ruleId, enabled)
    if (res.data?.code === 0) {
      return res.data.data?.enabled ?? enabled
    }
    throw new Error('toggle failed')
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  gateaiSharedStore.toggleInterlockRule(ruleId, enabled)
  return delay(enabled)
}

export async function updateInterlockRule(ruleId: number, patch: Partial<GateInterlockRule>) {
  try {
    const res = await updateInterlockRuleApi(ruleId, toInterlockUpdatePayload(patch))
    if (res.data?.code === 0) return res.data
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  return delay(gateaiSharedStore.updateInterlockRule(ruleId, patch))
}

export async function createInterlockRule(
  data: Omit<GateInterlockRule, 'id' | 'trigger_count_7d'>,
): Promise<GateInterlockRule> {
  // POST 创建请求体：直接发所有字段，不用 toInterlockUpdatePayload（那是给 PUT 部分更新用的）
  const payload: Record<string, unknown> = {
    rule_code: data.rule_code,
    rule_name: data.rule_name,
    description: data.description ?? '',
    enabled: data.enabled ?? true,
    priority: data.priority ?? 1,
    reservoir_id: data.reservoir_id ?? null,
    trigger_conditions: data.trigger_conditions || {},
    constraint_action: data.constraint_action || {},
  }
  if (import.meta.env.DEV) {
    console.log('[GateInterlock] POST 请求体:', JSON.stringify(payload, null, 2))
  }
  try {
    const res = await http.post<ApiResponse<GateInterlockRule>>(
      `${V1}/settings/gate-interlock/rules`,
      payload,
      { silent: true } as any,
    )
    if (res.data?.code === 0 && res.data.data) {
      return res.data.data
    }
    // 后端返回了 code !== 0 的业务错误
    const msg = res.data?.msg || '未知错误'
    console.warn('[GateInterlock] 创建失败:', msg, res.data)
    throw new Error(msg)
  } catch (e) {
    if (!GATEAI_MOCK_FALLBACK) throw e
  }
  return delay(gateaiSharedStore.createInterlockRule(data))
}

export function reorderInterlockRules(orderedIds: number[]) {
  gateaiSharedStore.reorderInterlockRules(orderedIds)
  return delay(null)
}

function parseOpening(value: string | number | undefined): number {
  const n = Number(value)
  if (Number.isNaN(n)) return 0
  return n <= 1 ? +(n * 100).toFixed(1) : +n.toFixed(1)
}

function parseLevel(value: string | number | undefined): number {
  const n = Number(value)
  return Number.isNaN(n) ? 0 : +n.toFixed(2)
}

function toDateParam(value: string): string {
  return /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : value.slice(0, 10)
}

function normalizeInterlockLog(raw: GateInterlockLogApiItem): GateInterlockLog {
  const openingsBefore = [
    parseOpening(raw.gate1_opening_before),
    parseOpening(raw.gate2_opening_before),
    parseOpening(raw.gate3_opening_before),
  ]
  const openingsAfter = [
    parseOpening(raw.gate1_opening_after),
    parseOpening(raw.gate2_opening_after),
    parseOpening(raw.gate3_opening_after),
  ]
  const changedGates = openingsBefore
    .map((before, index) => (before !== openingsAfter[index] ? index : -1))
    .filter((index) => index >= 0)

  const ruleName = raw.rule?.rule_name ?? raw.action_detail?.triggered_rule ?? '互锁规则'
  const ruleCode = raw.rule?.rule_code ?? raw.action_detail?.triggered_rule

  return {
    id: raw.id,
    trigger_time: raw.trigger_time,
    reservoir_id: raw.reservoir_id,
    reservoir_name: reservoirNameById(raw.reservoir_id),
    rule_name: ruleName,
    rule_code: ruleCode,
    decision_id: raw.decision_id,
    upstream_level: parseLevel(raw.upstream_level),
    downstream_level: parseLevel(raw.downstream_level),
    inflow_rate: parseLevel(raw.inflow_rate),
    openings_before: openingsBefore,
    openings_after: openingsAfter,
    changed_gates: changedGates,
    reason: raw.action_detail?.reason ?? ruleName,
  }
}

export async function fetchInterlockLogs(params?: {
  reservoirId?: number
  ruleCodes?: string[]
  startTime?: string
  endTime?: string
  page?: number
  pageSize?: number
}): Promise<GateInterlockLog[]> {
  try {
    const res = await getInterlockLogs({
      reservoir_id: params?.reservoirId ?? 1,
      page: params?.page ?? 1,
      page_size: params?.pageSize ?? 50,
      start_time: params?.startTime ? toDateParam(params.startTime) : undefined,
      end_time: params?.endTime ? toDateParam(params.endTime) : undefined,
    })
    if (res.data?.code === 0 && res.data.data) {
      let list = (res.data.data.list ?? []).map(normalizeInterlockLog)
      if (params?.ruleCodes?.length) {
        list = list.filter((item) => params.ruleCodes!.includes(item.rule_code ?? ''))
      }
      return list
    }
  } catch (e) {
    if (!GATEAI_MOCK_FALLBACK) throw e
  }
  return delay(gateaiSharedStore.getInterlockLogs(params))
}

export async function fetchInterlockStats(
  reservoirId: number,
): Promise<{ enabled_count: number; trigger_24h: number; trigger_7d: number }> {
  try {
    const [statsRes, rulesRes] = await Promise.all([
      getInterlockStats({ reservoir_id: reservoirId, days: 7 }),
      getInterlockRules({ reservoir_id: reservoirId }),
    ])
    if (statsRes.data?.code === 0 && Array.isArray(statsRes.data.data)) {
      const stats = normalizeInterlockStats(statsRes.data.data)
      const rules = rulesRes.data?.code === 0 && rulesRes.data.data
        ? normalizeInterlockRules(rulesRes.data.data)
        : []
      return {
        enabled_count: rules.filter((r) => r.enabled).length,
        trigger_24h: stats.filter((s) => {
          if (!s.last_triggered) return false
          const t = new Date(s.last_triggered).getTime()
          return !isNaN(t) && Date.now() - t < 86400000
        }).reduce((sum, s) => sum + s.trigger_count, 0),
        trigger_7d: stats.reduce((sum, s) => sum + (s.trigger_count || 0), 0),
      }
    }
  } catch (e) { if (!GATEAI_MOCK_FALLBACK) throw e }
  return delay(gateaiSharedStore.getInterlockStats(reservoirId))
}

/** 仪表盘互锁摘要：24h 触发数 + 最近触发的规则 */
export async function fetchInterlockDashboardSummary(reservoirId: number): Promise<{
  trigger_24h: number
  recent_rule: { name: string; time: string } | null
}> {
  try {
    const now = new Date().toISOString().replace('T', ' ').slice(0, 19)
    const weekAgo = new Date(Date.now() - 7 * 86400000).toISOString().replace('T', ' ').slice(0, 19)
    const [statsRes, logsRes] = await Promise.allSettled([
      getInterlockStats({ reservoir_id: reservoirId, days: 7 }),
      getInterlockLogs({
        reservoir_id: reservoirId,
        page: 1,
        page_size: 1,
        start_time: weekAgo.slice(0, 10),
        end_time: now.slice(0, 10),
      }),
    ])

    let trigger_24h = 0
    if (statsRes.status === 'fulfilled' && statsRes.value.data?.code === 0 && Array.isArray(statsRes.value.data.data)) {
      const stats = normalizeInterlockStats(statsRes.value.data.data)
      trigger_24h = stats.filter((s) => {
        if (!s.last_triggered) return false
        const t = new Date(s.last_triggered).getTime()
        return !isNaN(t) && Date.now() - t < 86400000
      }).reduce((sum, s) => sum + s.trigger_count, 0)
    }

    let recent_rule: { name: string; time: string } | null = null
    if (logsRes.status === 'fulfilled' && logsRes.value.data?.code === 0 && logsRes.value.data.data) {
      const list = (logsRes.value.data.data.list ?? []).map(normalizeInterlockLog)
      if (list.length > 0) {
        recent_rule = {
          name: list[0].rule_name,
          time: list[0].trigger_time,
        }
      }
    }

    return { trigger_24h, recent_rule }
  } catch (e) {
    if (!GATEAI_MOCK_FALLBACK) throw e
  }

  const stats = gateaiSharedStore.getInterlockStats(reservoirId)
  const logs = gateaiSharedStore.getInterlockLogs({ reservoirId })
  return {
    trigger_24h: stats.trigger_24h ?? 0,
    recent_rule:
      logs && logs.length > 0
        ? {
            name: logs[0].rule_name ?? '—',
            time: logs[0].trigger_time ?? new Date().toISOString(),
          }
        : null,
  }
}

export function fetchEdgeSyncStatus(edgeNodeId: number) {
  // 边缘同步状态暂无后端接口，使用 Mock
  return delay(gateaiSharedStore.getEdgeSyncStatus(edgeNodeId))
}

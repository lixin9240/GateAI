// ============================================================
// 数字孪生 — 场景 / 故障复盘 API 字段映射
// ============================================================
import { getScenePreset } from '@/constants/simulation'
import type {
  FaultConclusion,
  FaultReview,
  FaultTimelineEvent,
  SimulationParams,
  SimulationRealtimeData,
  SimulationReport,
  SimulationResultData,
  SimulationResultPoint,
  SimulationResultSummary,
  SimulationScene,
  SimulationScenarioItem,
  SimulationSummary,
} from '@/types/simulation'

export interface BackendScenarioItem {
  id: number
  name: string
  type: string
  description?: string | null
  status: string
  model_id?: number | null
  scenario_config?: Record<string, unknown> | null
  duration?: number
  speed?: number
  created_by?: number
  created_at?: string
  updated_at?: string
  usage_count?: number
}

export function mapBackendScenario(raw: BackendScenarioItem): SimulationScenarioItem {
  return {
    id: raw.id,
    name: raw.name,
    type: raw.type,
    description: raw.description ?? null,
    status: raw.status,
    model_id: raw.model_id ?? null,
    duration: raw.duration,
    speed: raw.speed,
    scenario_config: raw.scenario_config ?? null,
    created_at: raw.created_at,
    updated_at: raw.updated_at,
    usage_count: raw.usage_count,
  }
}

// ---------- 9.6 / 9.7 故障复盘 ----------

export interface BackendIncidentItem {
  id: number
  incident_name: string
  severity: string
  equipment_id?: number
  occurred_at: string
  resolved_at?: string | null
  duration?: number
  root_cause?: string | null
  description?: string | null
  raw_data?: Record<string, unknown> | null
  scenario_config?: Record<string, unknown> | null
}

/** 统一后端故障字段（部分接口用 name 而非 incident_name） */
export function normalizeBackendIncident(raw: Record<string, unknown>): BackendIncidentItem {
  const id = Number(raw.id)
  const incident_name =
    String(raw.incident_name ?? raw.name ?? raw.fault_name ?? raw.title ?? '').trim() ||
    `故障 #${id}`
  return {
    id,
    incident_name,
    severity: String(raw.severity ?? 'medium'),
    equipment_id: raw.equipment_id != null ? Number(raw.equipment_id) : undefined,
    occurred_at: String(raw.occurred_at ?? raw.created_at ?? new Date().toISOString()),
    resolved_at: raw.resolved_at != null ? String(raw.resolved_at) : null,
    duration: raw.duration != null ? Number(raw.duration) : undefined,
    root_cause: raw.root_cause != null ? String(raw.root_cause) : null,
    description: raw.description != null ? String(raw.description) : null,
    raw_data: (raw.raw_data as Record<string, unknown>) ?? null,
    scenario_config: (raw.scenario_config as Record<string, unknown>) ?? null,
  }
}

const SEVERITY_LABEL: Record<string, string> = {
  low: '低',
  medium: '中',
  high: '高',
  critical: '严重',
}

function formatIncidentTime(iso?: string | null): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleString('zh-CN', { hour12: false })
}

function formatDurationSec(sec?: number): string {
  if (!sec || sec <= 0) return '—'
  const m = Math.floor(sec / 60)
  const s = sec % 60
  if (m > 0) return `${m}分${s}秒`
  return `${s}秒`
}

function inferSceneFromIncident(raw: BackendIncidentItem): SimulationScene {
  const name = (raw.incident_name ?? '').toLowerCase()
  if (
    name.includes('洪水') ||
    name.includes('暴雨') ||
    name.includes('汛') ||
    name.includes('水位') ||
    name.includes('超标') ||
    name.includes('超限')
  ) {
    return 'flood'
  }
  if (name.includes('枯水') || name.includes('生态') || name.includes('缺水')) return 'dry'
  if (raw.severity === 'critical' || raw.severity === 'high') return 'flood'
  if (raw.severity === 'low') return 'dry'
  return 'custom'
}

function num(v: unknown, fallback: number): number {
  const n = typeof v === 'number' ? v : parseFloat(String(v ?? ''))
  return Number.isFinite(n) ? n : fallback
}

function optionalNum(v: unknown): number | undefined {
  if (v == null || v === '') return undefined
  const n = typeof v === 'number' ? v : parseFloat(String(v))
  return Number.isFinite(n) ? n : undefined
}

function hasHydraulicSummary(summary: SimulationResultSummary): boolean {
  return (
    summary.max_upstream_level != null ||
    summary.min_upstream_level != null ||
    summary.max_inflow_rate != null ||
    summary.max_gate_opening != null
  )
}

function pickSummaryFields(raw: Record<string, unknown>): SimulationResultSummary {
  return {
    max_upstream_level: optionalNum(
      raw.max_upstream_level ?? raw.maxUpstreamLevel ?? raw.max_water_level ?? raw.maxWaterLevel,
    ),
    min_upstream_level: optionalNum(
      raw.min_upstream_level ?? raw.minUpstreamLevel ?? raw.min_water_level ?? raw.minWaterLevel,
    ),
    max_downstream_level: optionalNum(
      raw.max_downstream_level ?? raw.maxDownstreamLevel ?? raw.max_downstream ?? raw.maxDownstream,
    ),
    max_inflow_rate: optionalNum(
      raw.max_inflow_rate ?? raw.maxInflowRate ?? raw.max_inflow ?? raw.maxInflow,
    ),
    max_outflow_rate: optionalNum(
      raw.max_outflow_rate ?? raw.maxOutflowRate ?? raw.max_outflow ?? raw.maxOutflow,
    ),
    max_gate_opening: optionalNum(
      raw.max_gate_opening ?? raw.maxGateOpening ?? raw.max_opening ?? raw.maxOpening,
    ),
    total_discharge: optionalNum(
      raw.total_discharge ?? raw.totalDischarge ?? raw.discharge_total ?? raw.dischargeTotal,
    ),
    total_energy: optionalNum(
      raw.total_energy ?? raw.totalEnergy ?? raw.energy_total ?? raw.energyTotal ?? raw.power_kwh,
    ),
    anomaly_count: optionalNum(raw.anomaly_count ?? raw.anomalyCount ?? raw.anomalies),
  }
}

function normalizeResultPoint(item: unknown, index: number): SimulationResultPoint | null {
  if (!item || typeof item !== 'object') return null
  const p = item as Record<string, unknown>
  const valuesSrc = (p.values ?? p.metrics ?? p.data ?? p) as Record<string, unknown>
  const values = {
    upstream_level: optionalNum(
      valuesSrc.upstream_level ?? valuesSrc.upstreamLevel ?? valuesSrc.water_level ?? valuesSrc.waterLevel,
    ),
    downstream_level: optionalNum(
      valuesSrc.downstream_level ?? valuesSrc.downstreamLevel ?? valuesSrc.downstream,
    ),
    inflow_rate: optionalNum(
      valuesSrc.inflow_rate ?? valuesSrc.inflowRate ?? valuesSrc.inflow ?? valuesSrc.flow,
    ),
    outflow_rate: optionalNum(valuesSrc.outflow_rate ?? valuesSrc.outflowRate ?? valuesSrc.outflow),
    gate_opening: optionalNum(
      valuesSrc.gate_opening ?? valuesSrc.gateOpening ?? valuesSrc.opening ?? valuesSrc.gate,
    ),
    power_output: optionalNum(valuesSrc.power_output ?? valuesSrc.powerOutput ?? valuesSrc.power),
  }
  if (!Object.values(values).some((v) => v != null)) return null
  return {
    id: optionalNum(p.id) ?? index,
    timestamp: String(p.timestamp ?? p.time ?? p.created_at ?? p.createdAt ?? ''),
    values,
  }
}

function pointsFromMetricSeries(layer: Record<string, unknown>): SimulationResultPoint[] {
  const upstream = layer.upstream_level ?? layer.upstreamLevel ?? layer.water_level
  if (!Array.isArray(upstream) || !upstream.length) return []
  const timestamps = (layer.timestamp ?? layer.timestamps ?? layer.time ?? layer.times) as unknown[]
  const inflow = (layer.inflow_rate ?? layer.inflowRate ?? layer.inflow) as unknown[]
  const downstream = (layer.downstream_level ?? layer.downstreamLevel) as unknown[]
  const gate = (layer.gate_opening ?? layer.gateOpening ?? layer.opening) as unknown[]
  const outflow = (layer.outflow_rate ?? layer.outflowRate ?? layer.outflow) as unknown[]
  const power = (layer.power_output ?? layer.powerOutput ?? layer.power) as unknown[]

  return upstream
    .map((lv, i) =>
      normalizeResultPoint(
        {
          id: i,
          timestamp: timestamps?.[i],
          values: {
            upstream_level: lv,
            downstream_level: downstream?.[i],
            inflow_rate: inflow?.[i],
            outflow_rate: outflow?.[i],
            gate_opening: gate?.[i],
            power_output: power?.[i],
          },
        },
        i,
      ),
    )
    .filter((p): p is SimulationResultPoint => p != null)
}

/** 从时序点聚合摘要（后端只返回 points 时使用） */
export function computeSummaryFromPoints(points: SimulationResultPoint[]): SimulationResultSummary {
  if (!points.length) return {}

  const levels = points
    .map((p) => optionalNum(p.values.upstream_level))
    .filter((v): v is number => v != null)
  const downstream = points
    .map((p) => optionalNum(p.values.downstream_level))
    .filter((v): v is number => v != null)
  const flows = points
    .map((p) => optionalNum(p.values.inflow_rate))
    .filter((v): v is number => v != null)
  const outflows = points
    .map((p) => optionalNum(p.values.outflow_rate))
    .filter((v): v is number => v != null)
  const gates = points
    .map((p) => optionalNum(p.values.gate_opening))
    .filter((v): v is number => v != null)
  const powers = points
    .map((p) => optionalNum(p.values.power_output))
    .filter((v): v is number => v != null)

  const flowForTotals = flows.length ? flows : outflows
  const totalFlow = flowForTotals.reduce((sum, v) => sum + v, 0)
  const totalPower = powers.reduce((sum, v) => sum + v, 0)

  return {
    max_upstream_level: levels.length ? Math.max(...levels) : undefined,
    min_upstream_level: levels.length ? Math.min(...levels) : undefined,
    max_downstream_level: downstream.length ? Math.max(...downstream) : undefined,
    max_inflow_rate: flows.length ? Math.max(...flows) : undefined,
    max_outflow_rate: outflows.length ? Math.max(...outflows) : undefined,
    max_gate_opening: gates.length ? Math.max(...gates) : undefined,
    total_discharge: flowForTotals.length ? Math.round(totalFlow * 0.35) : undefined,
    total_energy: totalPower > 0 ? Math.round(totalPower) : flowForTotals.length
      ? Math.round(totalFlow * 0.8 / 3600)
      : undefined,
    anomaly_count: 0,
  }
}

/** 归一化 9.4/9.7 仿真结果接口（兼容 snake/camel、嵌套 data、仅 points 等后端形态） */
export function normalizeSimulationResultData(raw: unknown): SimulationResultData {
  if (Array.isArray(raw)) {
    const points = raw
      .map((item, index) => normalizeResultPoint(item, index))
      .filter((p): p is SimulationResultPoint => p != null)
    return {
      summary: computeSummaryFromPoints(points),
      total: points.length,
      points,
    }
  }

  const root = raw && typeof raw === 'object' ? (raw as Record<string, unknown>) : {}
  const layer =
    root.data && typeof root.data === 'object' && !Array.isArray(root.data)
      ? (root.data as Record<string, unknown>)
      : root

  const summaryRaw = (layer.summary ??
    layer.stats ??
    layer.summary_stats ??
    layer.aggregates ??
    layer.result_summary ??
    {}) as Record<string, unknown>

  let summary: SimulationResultSummary = {
    ...pickSummaryFields(layer),
    ...pickSummaryFields(summaryRaw),
  }

  const pointsRaw =
    layer.points ??
    layer.time_series ??
    layer.series ??
    layer.records ??
    layer.metrics_list ??
    layer.data_points

  let points = Array.isArray(pointsRaw)
    ? pointsRaw
        .map((item, index) => normalizeResultPoint(item, index))
        .filter((p): p is SimulationResultPoint => p != null)
    : pointsFromMetricSeries(layer)

  if (!points.length && Array.isArray(layer.metrics)) {
    points = layer.metrics
      .map((item, index) => normalizeResultPoint(item, index))
      .filter((p): p is SimulationResultPoint => p != null)
  }

  if (!hasHydraulicSummary(summary) && points.length) {
    summary = { ...computeSummaryFromPoints(points), ...summary }
  }

  const total = optionalNum(layer.total ?? layer.count ?? layer.point_count) ?? points.length

  return {
    summary,
    total,
    points,
  }
}

/** 页面当前仿真状态 → 结果摘要（后端 result 为空时的兜底） */
export function summaryFromLocalSimStatus(
  simStatus?: SimulationRealtimeData,
  simParams?: SimulationParams,
  gateOpening?: number,
): SimulationResultSummary {
  const levels = simStatus?.historyLevels?.map((h) => h.value) ?? []
  const flows = simStatus?.historyFlows?.map((h) => h.value) ?? []
  const currentLevel = simStatus?.currentLevel ?? simParams?.initialLevel ?? 380
  const currentFlow = simStatus?.currentFlow ?? simParams?.inflowRate ?? 1850
  const elapsed = simStatus?.elapsedSec ?? (simParams?.durationMin ?? 60) * 60
  const opening = gateOpening ?? simStatus?.currentOpening ?? 45

  return {
    max_upstream_level: levels.length ? Math.max(...levels, currentLevel) : currentLevel,
    min_upstream_level: levels.length ? Math.min(...levels, currentLevel) : currentLevel,
    max_downstream_level: simStatus?.currentDownstreamLevel,
    max_inflow_rate: flows.length ? Math.max(...flows, currentFlow) : currentFlow,
    max_gate_opening: opening,
    total_discharge: Math.round(currentFlow * elapsed * 0.35),
    total_energy: Math.round(currentFlow * 0.8 * elapsed / 3600),
    anomaly_count: 0,
  }
}

/** 合并后端 result + 本地仿真状态，保证报告有可展示数据 */
export function resolveSimulationResultSummary(
  data: SimulationResultData,
  context?: {
    simStatus?: SimulationRealtimeData
    params?: SimulationParams
    gateOpening?: number
  },
): SimulationResultSummary {
  let summary: SimulationResultSummary = { ...(data.summary ?? {}) }

  if (data.points?.length) {
    summary = { ...computeSummaryFromPoints(data.points), ...summary }
  }

  const local =
    context?.simStatus || context?.params
      ? summaryFromLocalSimStatus(context.simStatus, context.params, context.gateOpening)
      : undefined

  if (!hasHydraulicSummary(summary) && local) {
    return { ...local, ...summary }
  }

  if (local) {
    return fillHydraulicGaps(summary, local)
  }

  return summary
}

function fillHydraulicGaps(
  base: SimulationResultSummary,
  fill: SimulationResultSummary,
): SimulationResultSummary {
  return {
    ...base,
    max_upstream_level: base.max_upstream_level ?? fill.max_upstream_level,
    min_upstream_level: base.min_upstream_level ?? fill.min_upstream_level,
    max_downstream_level: base.max_downstream_level ?? fill.max_downstream_level,
    max_inflow_rate: base.max_inflow_rate ?? fill.max_inflow_rate,
    max_outflow_rate: base.max_outflow_rate ?? fill.max_outflow_rate,
    max_gate_opening: base.max_gate_opening ?? fill.max_gate_opening,
    total_discharge: base.total_discharge ?? fill.total_discharge,
    total_energy: base.total_energy ?? fill.total_energy,
    anomaly_count: base.anomaly_count ?? fill.anomaly_count ?? 0,
  }
}

function buildConclusion(raw: BackendIncidentItem): FaultConclusion | null {
  if (!raw.root_cause) return null
  return {
    rootCause: raw.root_cause,
    improvements: (raw.description as string) || '',
    responsibleDept: '',
    reviewedBy: '',
    reviewedAt: raw.resolved_at ?? raw.occurred_at,
  }
}

export function buildIncidentTimeline(raw: BackendIncidentItem): FaultTimelineEvent[] {
  const fromRaw = raw.raw_data?.timeline
  if (Array.isArray(fromRaw) && fromRaw.length) {
    return fromRaw.map((item) => {
      const row = item as Record<string, unknown>
      return {
        time: String(row.time ?? row.timestamp ?? '—'),
        event: String(row.event ?? row.description ?? row.msg ?? ''),
      }
    })
  }

  const events: FaultTimelineEvent[] = []
  if (raw.occurred_at) {
    events.push({
      time: formatIncidentTime(raw.occurred_at),
      event: `故障发生：${raw.incident_name}`,
    })
  }
  if (raw.duration) {
    events.push({
      time: formatIncidentTime(raw.occurred_at),
      event: `持续 ${formatDurationSec(raw.duration)}`,
    })
  }
  if (raw.resolved_at) {
    events.push({
      time: formatIncidentTime(raw.resolved_at),
      event: '故障恢复',
    })
  }
  if (raw.root_cause) {
    events.push({
      time: formatIncidentTime(raw.resolved_at ?? raw.occurred_at),
      event: `根因分析：${raw.root_cause}`,
    })
  }
  return events.length ? events : [{ time: '—', event: raw.incident_name || '故障记录' }]
}

export function mapBackendIncident(raw: BackendIncidentItem): FaultReview {
  const reviewed = Boolean(raw.root_cause)
  const severityLabel = SEVERITY_LABEL[raw.severity] ?? raw.severity
  const equipmentLabel = raw.equipment_id ? `设备 #${raw.equipment_id}` : '关联设备'
  return {
    id: raw.id,
    alarmId: raw.equipment_id ?? 0,
    faultType: raw.incident_name,
    impactScope: `${equipmentLabel} · ${severityLabel}级`,
    reviewed,
    status: reviewed ? 'reviewed' : 'pending',
    createdAt: raw.occurred_at,
    timeline: buildIncidentTimeline(raw),
    conclusion: buildConclusion(raw),
  }
}

/** 从场景库条目提取仿真初始参数 */
export function scenarioToSimulationParams(
  item: SimulationScenarioItem,
): SimulationParams & { gateOpening?: number } {
  const preset = getScenePreset('custom')
  const data = item.scenario_config ?? {}
  const durationRaw = item.duration ?? data.duration ?? data.duration_sec ?? data.durationMin
  let durationMin = preset.durationMin
  if (durationRaw != null) {
    const d = num(durationRaw, preset.durationMin)
    durationMin = d > 180 ? Math.round(d / 60) : Math.round(d)
  }
  return {
    scene: 'custom',
    initialLevel: num(
      data.initial_water_level ?? data.initialLevel ?? data.upstream_level,
      preset.initialLevel,
    ),
    inflowRate: num(data.inflow_rate ?? data.inflowRate ?? data.inflow, preset.inflowRate),
    durationMin: Math.min(240, Math.max(10, durationMin)),
    gateOpening: num(data.gate_opening ?? data.gateOpening, preset.gateOpening),
  }
}

/** 从故障 raw_data / scenario_config 提取仿真初始参数 */
export function incidentToSimulationParams(raw: BackendIncidentItem): SimulationParams {
  const scene = inferSceneFromIncident(raw)
  const preset = getScenePreset(scene)
  const data = {
    ...(raw.scenario_config ?? {}),
    ...(raw.raw_data ?? {}),
  }
  const durationRaw = data.duration ?? data.duration_sec ?? data.durationMin
  let durationMin = preset.durationMin
  if (durationRaw != null) {
    const d = num(durationRaw, preset.durationMin)
    durationMin = d > 180 ? Math.round(d / 60) : Math.round(d)
  } else if (raw.duration) {
    durationMin = Math.max(10, Math.round(raw.duration / 60))
  }

  return {
    scene,
    initialLevel: num(
      data.initial_water_level ?? data.initialLevel ?? data.upstream_level,
      preset.initialLevel,
    ),
    inflowRate: num(data.inflow_rate ?? data.inflowRate ?? data.inflow, preset.inflowRate),
    durationMin: Math.min(240, Math.max(10, durationMin)),
    gateOpening: num(data.gate_opening ?? data.gateOpening, preset.gateOpening),
  }
}

/** 从故障复盘条目推断仿真初始参数（本地/Mock 导入） */
export function faultReviewToSimulationParams(
  review: FaultReview,
): SimulationParams & { gateOpening: number } {
  const text = `${review.faultType} ${review.impactScope}`
  if (/水位|高水|超标|超限|洪水|汛|漫坝/.test(text)) {
    const preset = getScenePreset('flood')
    return { ...preset, scene: 'flood', gateOpening: preset.gateOpening }
  }
  if (/生态|枯水|流量不足|缺水|来水偏少/.test(text)) {
    const preset = getScenePreset('dry')
    return { ...preset, scene: 'dry', gateOpening: preset.gateOpening }
  }
  if (/暴雨|降雨|持续雨/.test(text)) {
    const preset = getScenePreset('rainstorm')
    return { ...preset, scene: 'rainstorm', gateOpening: preset.gateOpening }
  }
  if (/阀门|执行失败|泄洪|表孔|底孔/.test(text)) {
    const preset = getScenePreset('normal')
    return {
      ...preset,
      scene: 'normal',
      initialLevel: 380.2,
      gateOpening: 0,
    }
  }
  const preset = getScenePreset('normal')
  return { ...preset, scene: 'normal', gateOpening: preset.gateOpening }
}

const DEFAULT_RESERVOIR_ID = 1

export function toBackendIncidentQuery(params: {
  pageNum: number
  pageSize: number
  type?: string
  startTime?: string
  endTime?: string
}) {
  return {
    page: params.pageNum,
    page_size: params.pageSize,
    severity: params.type || undefined,
    start_time: params.startTime,
    end_time: params.endTime,
    reservoir_id: DEFAULT_RESERVOIR_ID,
  }
}

export interface BackendImportIncidentResult {
  incident_id?: number
  import_id?: string
  status?: string
  params?: Record<string, unknown>
  scenario_config?: Record<string, unknown>
}

/** POST /api/v1/simulation/import-incident 请求体（兼容 incident_id 与文档全字段两种后端实现） */
export function toBackendImportIncidentBody(incident: BackendIncidentItem) {
  const name = (incident.incident_name ?? '').trim() || `故障 #${incident.id}`
  return {
    incident_id: incident.id,
    incident_name: name,
    description: incident.description ?? undefined,
    severity: incident.severity ?? 'medium',
    equipment_id: incident.equipment_id ?? 1,
    occurred_at: incident.occurred_at ?? new Date().toISOString(),
    resolved_at: incident.resolved_at ?? undefined,
    raw_data: incident.raw_data ?? { incident_id: incident.id, incident_name: name },
    scenario_config: {
      name,
      auto_run: false,
      ...(incident.scenario_config ?? {}),
    },
  }
}

/** 后端 import-incident 响应 → 仿真初始参数
 *  Apifox 成功响应 data 仅含 incident_id / import_id / status，仿真参数从故障详情 incident 解析 */
export function mapImportIncidentResponse(
  payload: Record<string, unknown>,
  incident: BackendIncidentItem,
): SimulationParams & { gateOpening?: number } {
  const nested = (payload.params ?? payload.scenario_config ?? {}) as Record<string, unknown>
  const hasParams =
    nested.initial_water_level != null ||
    nested.initialLevel != null ||
    nested.inflow_rate != null ||
    nested.inflowRate != null ||
    nested.gate_opening != null ||
    nested.gateOpening != null

  if (hasParams) {
    return incidentToSimulationParams({
      ...incident,
      scenario_config: nested,
      raw_data: incident.raw_data ?? nested,
    })
  }
  return incidentToSimulationParams(incident)
}

/** 后端 result 接口 → 轮询用实时状态 */
export function mapResultDataToRealtime(
  data: SimulationResultData,
  _simulationId: string,
): SimulationRealtimeData {
  const points = data.points ?? []
  if (!points.length) {
    return {
      status: 'running',
      elapsedSec: 0,
      currentLevel: data.summary?.max_upstream_level ?? 380,
      currentDownstreamLevel: data.summary?.max_downstream_level ?? 278,
      currentFlow: data.summary?.max_inflow_rate ?? 1850,
      currentOpening: Math.round(data.summary?.max_gate_opening ?? 45),
      historyLevels: [],
      historyFlows: [],
    }
  }

  const last = points[points.length - 1].values
  const historyLevels = points.map((p, i) => ({
    time: i,
    value: p.values.upstream_level ?? 0,
  }))
  const historyFlows = points.map((p, i) => ({
    time: i,
    value: p.values.inflow_rate ?? 0,
  }))
  const finished = points.length >= (data.total ?? points.length)

  return {
    status: finished ? 'finished' : 'running',
    elapsedSec: historyLevels.length ? historyLevels[historyLevels.length - 1].time : 0,
    currentLevel: last.upstream_level ?? 0,
    currentDownstreamLevel: last.downstream_level ?? 0,
    currentFlow: last.inflow_rate ?? 0,
    currentOpening: Math.round(last.gate_opening ?? 0),
    historyLevels,
    historyFlows,
  }
}

// ---------- 9.7 / 9.8 仿真结果与报告 ----------

export interface BackendReportTask {
  report_id?: string
  status?: string
  download_url?: string | null
}

export function resultSummaryToSimulationSummary(
  summary: SimulationResultSummary,
): SimulationSummary {
  return {
    maxLevel: summary.max_upstream_level ?? 0,
    minLevel: summary.min_upstream_level ?? 0,
    totalDischarge: summary.total_discharge ?? 0,
    estimatedPower: summary.total_energy ?? 0,
  }
}

export function buildReportContent(
  simulationId: string,
  summary: SimulationResultSummary,
): string {
  const maxLv = summary.max_upstream_level?.toFixed(2) ?? '—'
  const minLv = summary.min_upstream_level?.toFixed(2) ?? '—'
  const gate = summary.max_gate_opening ?? '—'
  const energy = summary.total_energy ?? '—'
  const anomaly = summary.anomaly_count ?? 0
  const discharge = summary.total_discharge ?? '—'
  return `仿真任务 ${simulationId}：最高水位 ${maxLv} m，最低 ${minLv} m，最大开度 ${gate}%，总下泄 ${discharge} m³，发电 ${energy} kWh，异常 ${anomaly} 次。`
}

export function buildSimulationReport(params: {
  simulationId: string
  scene: SimulationScene
  simParams: SimulationParams
  resultSummary: SimulationResultSummary
  downloadUrl?: string | null
  operatorName: string
  reportId?: string
}): SimulationReport {
  const now = new Date()
  const numericId = Number(`${now.getTime()}`.slice(-9))
  return {
    id: numericId,
    runId: numericId,
    scene: params.scene,
    params: params.simParams,
    summary: resultSummaryToSimulationSummary(params.resultSummary),
    content: buildReportContent(params.simulationId, params.resultSummary),
    filePath: params.downloadUrl || `local:${params.simulationId}`,
    createdAt: now.toLocaleString('zh-CN', { hour12: false }),
    operatorName: params.operatorName,
  }
}

// ============================================================
// 数字孪生 — API 接口层（真实接口优先，失败回退 Mock）
// ============================================================
import http from './request'
import type { ApiResponse, PageResult } from '@/shared/types'
import type {
  SimulationParams,
  SimulationRealtimeData,
  SimulationReport,
  SimulationStartPayload,
  SimulationStartResult,
  SimulationScenarioItem,
  SimulationScenarioPayload,
  SimulationResultData,
  SimulationResultSummary,
  SimulationScene,
  AiModel,
  TrainingConfig,
  FaultReview,
  FaultConclusion,
} from '@/types/simulation'
import type { PhysicsGuardSummary } from '@/types/dispatch'
import { mockApi } from './mockStore'
import { isApiBusinessError, extractSimulationIdFromError, ApiBusinessError } from '@/utils/apiError'
import {
  mapBackendScenario,
  mapBackendIncident,
  incidentToSimulationParams,
  mapImportIncidentResponse,
  mapResultDataToRealtime,
  toBackendIncidentQuery,
  toBackendImportIncidentBody,
  normalizeBackendIncident,
  normalizeSimulationResultData,
  resolveSimulationResultSummary,
  summaryFromLocalSimStatus,
  buildSimulationReport,
  buildReportContent,
  resultSummaryToSimulationSummary,
  type BackendScenarioItem,
  type BackendIncidentItem,
  type BackendImportIncidentResult,
  type BackendReportTask,
} from './simulationAdapter'
import { MODEL_REGISTRY_LABEL } from '@/constants/simulation'
import {
  getModels as getSettingsModels,
  uploadModel as uploadSettingsModel,
  activateModel as activateSettingsModel,
} from './settings'
import type { ModelInfo } from '@/shared/types'

// ── ModelInfo（settings API）→ AiModel（simulation 类型）映射 ──
function modelInfoToAiModel(m: ModelInfo): AiModel {
  const typeMap: Record<string, AiModel['type']> = {
    lstm_prediction: 'LSTM',
    dqn_decision: 'DQN',
    fault_detection: 'LSTM',
    general: 'DQN',
  }
  const statusMap: Record<string, AiModel['status']> = {
    uploaded: 'inactive',
    validating: 'validating',
    ready: 'inactive',
    active: 'active',
    deprecated: 'inactive',
  }
  return {
    id: m.id,
    type: typeMap[m.type] ?? 'LSTM',
    version: m.version,
    filePath: '',
    status: statusMap[m.status] ?? 'inactive',
    metrics: {
      accuracy: m.accuracy ?? 0,
      overallScore: m.overall_score ?? 0,
      healthGrade: (m.health_grade as 'S' | 'A' | 'B' | 'C' | 'D') ?? 'B',
    },
    remark: null,
    createdAt: m.training_date ?? '',
    activatedAt: m.is_active ? new Date().toISOString() : null,
  }
}

/** 必须为 /v1，避免 env 为空时误请求 /api/simulation/* */
const V1_PREFIX = (import.meta.env.VITE_API_V1_PREFIX?.trim() || '/v1').replace(/\/$/, '') || '/v1'
// 场景列表 GET 无 v1；创建/更新/删除用 v1（Apifox §9.1 vs §9.2-9.4）
const SIM_V1_BASE = `${V1_PREFIX}/simulation`
const REPORTS_STORAGE_KEY = 'simulation_reports_v1'
const REPORT_BLOBS_STORAGE_KEY = 'simulation_report_blobs_v1'
const SCENARIO_SIM_TASK_KEY = 'simulation_scenario_task_map'
const SCENARIO_SIM_TASK_HISTORY_KEY = 'simulation_scenario_task_history'
const SIM_SELECTED_MODEL_KEY = 'simulation_selected_model_id'
const DEFAULT_RESERVOIR_ID = 1
const DEFAULT_MODEL_ID = 2
const DEFAULT_SCENARIO_ID = 1
/** 数字孪生是否使用本地 Mock（默认 false，对接真实后端） */
export const SIMULATION_USE_MOCK = import.meta.env.VITE_SIMULATION_MOCK === 'true'

/** 请求失败时不弹全局 toast（由页面自行处理） */
const SILENT_REQ = { silent: true } as const

/** 列表拉取后缓存，供详情 / 导入仿真回退 */
const incidentCache = new Map<number, BackendIncidentItem>()

function cacheIncidents(list: BackendIncidentItem[]) {
  list.forEach((item) => incidentCache.set(item.id, item))
}

function normalizeIncidentList(list: BackendIncidentItem[]): BackendIncidentItem[] {
  return list.map((item) =>
    normalizeBackendIncident(item as unknown as Record<string, unknown>),
  )
}

// 9.6 故障复盘 — 固定 GET /api/v1/simulation/incidents
const INCIDENTS_V1 = `${SIM_V1_BASE}/incidents`
/** Apifox: POST /api/v1/simulation/import-incident */
const IMPORT_INCIDENT_V1 = '/v1/simulation/import-incident'

async function postImportIncident(
  payload: Record<string, unknown>,
): Promise<ApiResponse<BackendImportIncidentResult>> {
  const res = await http.post<ApiResponse<BackendImportIncidentResult>>(
    IMPORT_INCIDENT_V1,
    payload,
    SILENT_REQ,
  )
  const body = unwrap(res)
  if (!body) throw new Error('导入故障复盘失败')
  return body
}

async function fetchIncidentById(id: number): Promise<BackendIncidentItem | null> {
  const cached = incidentCache.get(id)
  if (cached) return cached

  try {
    const res = await http.get<ApiResponse<BackendIncidentItem>>(`${INCIDENTS_V1}/${id}`, SILENT_REQ)
    const body = unwrap(res)
    if (body?.data) {
      const normalized = normalizeIncidentList([body.data])[0]
      cacheIncidents([normalized])
      return normalized
    }
  } catch {
    /* 详情接口不可用时回退列表检索 */
  }

  try {
    const res = await http.get<ApiResponse<PageResult<BackendIncidentItem>>>(INCIDENTS_V1, {
      params: { page: 1, page_size: 100, reservoir_id: DEFAULT_RESERVOIR_ID },
      ...SILENT_REQ,
    })
    const body = unwrap(res)
    const list = normalizeIncidentList(body?.data?.list ?? [])
    cacheIncidents(list)
    return list.find((item) => item.id === id) ?? null
  } catch {
    return null
  }
}

function unwrap<T>(res: { data: ApiResponse<T> }): ApiResponse<T> | null {
  if (res.data?.code === 0) return res.data
  return null
}

async function withMockFallback<T>(
  apiFn: () => Promise<ApiResponse<T>>,
  mockFn: () => Promise<ApiResponse<T>>,
): Promise<ApiResponse<T>> {
  try {
    return await apiFn()
  } catch (err) {
    if (!SIMULATION_USE_MOCK) throw err
    return mockFn()
  }
}

function loadStoredReports(): SimulationReport[] {
  try {
    const raw = localStorage.getItem(REPORTS_STORAGE_KEY)
    if (!raw) return []
    const list = JSON.parse(raw) as SimulationReport[]
    return Array.isArray(list) ? list : []
  } catch {
    return []
  }
}

function saveStoredReport(report: SimulationReport) {
  const list = loadStoredReports().filter((r) => r.id !== report.id)
  list.unshift(report)
  localStorage.setItem(REPORTS_STORAGE_KEY, JSON.stringify(list.slice(0, 50)))
}

function loadReportBlobs(): Record<string, string> {
  try {
    const raw = localStorage.getItem(REPORT_BLOBS_STORAGE_KEY)
    if (!raw) return {}
    const data = JSON.parse(raw) as Record<string, string>
    return data && typeof data === 'object' ? data : {}
  } catch {
    return {}
  }
}

function saveReportBlob(reportId: number, content: string) {
  const blobs = loadReportBlobs()
  blobs[String(reportId)] = content
  localStorage.setItem(REPORT_BLOBS_STORAGE_KEY, JSON.stringify(blobs))
}

export type SimulationReportContext = {
  scene?: SimulationScene
  params?: SimulationParams
  operatorName?: string
  simStatus?: SimulationRealtimeData
  gateOpening?: number
}

function buildLocalResultSummary(
  simStatus?: SimulationRealtimeData,
  simParams?: SimulationParams,
  gateOpening?: number,
): SimulationResultSummary {
  return summaryFromLocalSimStatus(simStatus, simParams, gateOpening)
}

function extractSimulationIdFromContent(content: string): string | null {
  const m = content.match(/仿真任务\s+(SIM-[^\s：:]+)/)
  return m?.[1] ?? null
}

/** 修复 localStorage 中缺 filePath / 摘要为空的旧报告 */
function repairStoredReport(report: SimulationReport): SimulationReport {
  const simId = extractSimulationIdFromContent(report.content)
  const filePath = report.filePath || (simId ? `local:${simId}` : 'local:report')
  const needsRepair = report.content.includes('最高水位 —') || report.summary.maxLevel <= 0

  if (needsRepair && report.params?.initialLevel) {
    const resultSummary = summaryFromLocalSimStatus(undefined, report.params, undefined)
    const repaired: SimulationReport = {
      ...report,
      filePath,
      summary: resultSummaryToSimulationSummary(resultSummary),
      content: simId ? buildReportContent(simId, resultSummary) : report.content,
    }
    saveReportBlob(
      repaired.id,
      buildLocalReportDocument(repaired, simId ?? String(repaired.runId)),
    )
    return repaired
  }

  return { ...report, filePath }
}

function buildLocalReportDocument(report: SimulationReport, simulationId: string): string {
  return [
    '向家坝水电站 — 方案评估报告',
    '================================',
    `报告编号: ${report.id}`,
    `仿真任务: ${simulationId}`,
    `生成时间: ${report.createdAt}`,
    `操作人: ${report.operatorName}`,
    `仿真场景: ${report.scene}`,
    '',
    '【工况参数】',
    `初始水位: ${report.params.initialLevel} m`,
    `入库流量: ${report.params.inflowRate} m³/s`,
    `仿真时长: ${report.params.durationMin} min`,
    '',
    '【结果摘要】',
    `最高水位: ${report.summary.maxLevel.toFixed(2)} m`,
    `最低水位: ${report.summary.minLevel.toFixed(2)} m`,
    `总下泄量: ${report.summary.totalDischarge} m³`,
    `预估发电: ${report.summary.estimatedPower} kWh`,
    '',
    '【评估结论】',
    report.content,
    '',
    '—— 本报告由数字孪生仿真系统自动生成 ——',
  ].join('\n')
}

async function generateLocalReport(
  simulationId: string,
  context?: SimulationReportContext,
): Promise<ApiResponse<SimulationReport>> {
  const simParams = context?.params ?? {
    scene: context?.scene ?? 'normal',
    initialLevel: 380,
    inflowRate: 1850,
    durationMin: 60,
  }
  const resultSummary = buildLocalResultSummary(
    context?.simStatus,
    simParams,
    context?.gateOpening,
  )
  const report = buildSimulationReport({
    simulationId,
    scene: context?.scene ?? simParams.scene,
    simParams,
    resultSummary,
    downloadUrl: `local:${simulationId}`,
    operatorName: context?.operatorName ?? '当前用户',
  })
  saveReportBlob(report.id, buildLocalReportDocument(report, simulationId))
  saveStoredReport(report)
  return {
    code: 0,
    msg: '报告已生成',
    success: true,
    trace_id: 'local-report',
    data: report,
  }
}

/** 生产/联调：后端仿真接口统一在 /api/v1/simulation/* */
function simPaths(suffix: string): string[] {
  return [`${SIM_V1_BASE}${suffix}`]
}

function normalizeStartResult(raw: Record<string, unknown> | SimulationStartResult): SimulationStartResult | null {
  const simulation_id = String(
    raw.simulation_id ?? raw.simulationId ?? raw.task_no ?? raw.task_id ?? '',
  )
  if (!simulation_id) return null
  return {
    simulation_id,
    status: String(raw.status ?? 'running'),
    start_time: raw.start_time as string | undefined,
    estimated_end_time: raw.estimated_end_time as string | undefined,
    ws_endpoint: String(raw.ws_endpoint ?? raw.wsEndpoint ?? ''),
  }
}

function pickActiveModelId(models: AiModel[]): number {
  const remembered = getRememberedSimulationModel()
  if (remembered != null && models.some((m) => m.id === remembered)) {
    return remembered
  }
  const active = models.find((m) => m.status === 'active')
  if (active) return active.id
  const ready = models.find((m) => m.status === 'inactive' || m.status === 'validating')
  if (ready) return ready.id
  return remembered ?? DEFAULT_MODEL_ID
}

function runnableScenarios(scenarios: SimulationScenarioItem[]): SimulationScenarioItem[] {
  const active = scenarios.filter((s) => s.status === 'active')
  if (active.length) return active
  return scenarios.filter((s) => s.status !== 'draft')
}

async function getSimPaths<T>(
  paths: string[],
  config?: { params?: Record<string, unknown> },
): Promise<ApiResponse<T>> {
  let lastErr: unknown
  for (const path of paths) {
    try {
      const res = await http.get<ApiResponse<T>>(path, { ...config, ...SILENT_REQ })
      const body = unwrap(res)
      if (body) return body
    } catch (err) {
      lastErr = err
    }
  }
  throw lastErr ?? new Error('request failed')
}

function loadScenarioTaskMap(): Record<string, string> {
  try {
    const raw =
      localStorage.getItem(SCENARIO_SIM_TASK_KEY) ??
      sessionStorage.getItem(SCENARIO_SIM_TASK_KEY)
    if (!raw) return {}
    const map = JSON.parse(raw) as Record<string, string>
    return map && typeof map === 'object' ? map : {}
  } catch {
    return {}
  }
}

function saveScenarioTaskMap(map: Record<string, string>) {
  localStorage.setItem(SCENARIO_SIM_TASK_KEY, JSON.stringify(map))
  sessionStorage.setItem(SCENARIO_SIM_TASK_KEY, JSON.stringify(map))
}

function loadScenarioTaskHistory(scenarioId: number): string[] {
  try {
    const raw = localStorage.getItem(`${SCENARIO_SIM_TASK_HISTORY_KEY}_${scenarioId}`)
    if (!raw) return []
    const list = JSON.parse(raw) as string[]
    return Array.isArray(list) ? list.filter((id) => id && String(id).length > 0) : []
  } catch {
    return []
  }
}

function saveScenarioTaskHistory(scenarioId: number, simulationId: string) {
  const list = loadScenarioTaskHistory(scenarioId).filter((id) => id !== simulationId)
  list.push(simulationId)
  localStorage.setItem(
    `${SCENARIO_SIM_TASK_HISTORY_KEY}_${scenarioId}`,
    JSON.stringify(list.slice(-8)),
  )
}

/** 记录场景与后端仿真任务的对应关系（用于 40002 冲突时重置） */
export function rememberScenarioSimulation(scenarioId: number, simulationId: string) {
  const map = loadScenarioTaskMap()
  map[String(scenarioId)] = simulationId
  saveScenarioTaskMap(map)
  saveScenarioTaskHistory(scenarioId, simulationId)
}

export function getRememberedScenarioSimulation(scenarioId: number): string | null {
  return loadScenarioTaskMap()[String(scenarioId)] ?? null
}

/** 该场景曾启动过的任务 ID（本地缓存，用于无后端 task 字段时 reset） */
export function getRememberedScenarioTaskIds(scenarioId: number): string[] {
  const ids = new Set<string>()
  const latest = getRememberedScenarioSimulation(scenarioId)
  if (latest) ids.add(latest)
  for (const id of loadScenarioTaskHistory(scenarioId)) ids.add(id)
  return [...ids]
}

export function clearRememberedScenarioSimulation(scenarioId: number) {
  const map = loadScenarioTaskMap()
  delete map[String(scenarioId)]
  saveScenarioTaskMap(map)
  localStorage.removeItem(`${SCENARIO_SIM_TASK_HISTORY_KEY}_${scenarioId}`)
}

/** 仿真页选用的 AI 模型（模型版本管理导入后写入） */
export function rememberSimulationModel(modelId: number) {
  sessionStorage.setItem(SIM_SELECTED_MODEL_KEY, String(modelId))
}

export function getRememberedSimulationModel(): number | null {
  const raw = sessionStorage.getItem(SIM_SELECTED_MODEL_KEY)
  if (!raw) return null
  const id = Number(raw)
  return Number.isFinite(id) && id > 0 ? id : null
}

/** 40002 时解析已有任务 ID：error.data → 当前页 → 本地缓存 */
export function resolveConflictSimulationId(
  err: unknown,
  scenarioId: number,
  activeSimulationId?: string | null,
): string | null {
  return (
    extractSimulationIdFromError(err) ??
    (activeSimulationId && activeSimulationId.length > 0 ? activeSimulationId : null) ??
    getRememberedScenarioSimulation(scenarioId)
  )
}

/** 从场景详情 / 列表补查运行中任务 ID */
export async function fetchScenarioRunningTaskId(scenarioId: number): Promise<string | null> {
  try {
    const res = await http.get<ApiResponse<Record<string, unknown>>>(
      `${SIM_V1_BASE}/scenarios/${scenarioId}`,
      SILENT_REQ,
    )
    const body = unwrap(res)
    const data = body?.data
    if (data && typeof data === 'object') {
      const id = extractSimulationIdFromError(new ApiBusinessError(0, '', data))
      if (id) return id
    }
  } catch {
    /* 单条详情不可用时改查列表 */
  }

  try {
    const res = await http.get<ApiResponse<PageResult<BackendScenarioItem>>>(
      `${SIM_V1_BASE}/scenarios`,
      { params: { page: 1, page_size: 100 }, ...SILENT_REQ },
    )
    const body = unwrap(res)
    const row = (body?.data?.list ?? []).find((item) => item.id === scenarioId)
    if (row && typeof row === 'object') {
      return extractSimulationIdFromError(new ApiBusinessError(0, '', row as Record<string, unknown>))
    }
  } catch {
    /* ignore */
  }

  return null
}

async function tryResetSimulationTask(simulationId: string): Promise<boolean> {
  try {
    await resetSimulation(simulationId)
    return true
  } catch {
    return false
  }
}

/** 启动前静默清理该场景可能残留的后端任务 */
export async function clearScenarioRunningTask(
  scenarioId: number,
  options?: { err?: unknown; activeSimulationId?: string | null },
): Promise<void> {
  const taskIds = new Set<string>()
  for (const id of getRememberedScenarioTaskIds(scenarioId)) taskIds.add(id)
  const fromErr = options?.err
    ? resolveConflictSimulationId(options.err, scenarioId, options.activeSimulationId)
    : null
  if (fromErr) taskIds.add(fromErr)
  if (options?.activeSimulationId) taskIds.add(options.activeSimulationId)

  const remoteId = await fetchScenarioRunningTaskId(scenarioId)
  if (remoteId) taskIds.add(remoteId)

  for (const path of [
    `${SIM_V1_BASE}/scenarios/${scenarioId}/reset`,
    `${SIM_V1_BASE}/scenarios/${scenarioId}/stop`,
    `${SIM_V1_BASE}/scenarios/${scenarioId}/cancel`,
  ]) {
    try {
      await http.post(path, {}, SILENT_REQ)
    } catch {
      /* 部分后端未实现场景级 reset，忽略 */
    }
  }

  for (const id of taskIds) {
    await tryResetSimulationTask(id)
  }

  clearRememberedScenarioSimulation(scenarioId)
}

/** 40002 冲突时尽可能解析可 reset 的任务 ID */
export async function resolveRunningSimulationId(
  err: unknown,
  scenarioId: number,
  activeSimulationId?: string | null,
): Promise<string | null> {
  const cached = resolveConflictSimulationId(err, scenarioId, activeSimulationId)
  if (cached) return cached
  return fetchScenarioRunningTaskId(scenarioId)
}

async function postSimPaths<T>(paths: string[], data?: unknown): Promise<ApiResponse<T>> {
  let lastErr: unknown
  for (const path of paths) {
    try {
      const res = await http.post<ApiResponse<T>>(path, data, SILENT_REQ)
      const body = unwrap(res)
      if (body) return body
      lastErr = new Error('request failed')
    } catch (err) {
      lastErr = err
      if (isApiBusinessError(err)) throw err
    }
  }
  throw lastErr ?? new Error('request failed')
}

async function putSimPaths<T>(paths: string[], data?: unknown): Promise<ApiResponse<T>> {
  let lastErr: unknown
  for (const path of paths) {
    try {
      const res = await http.put<ApiResponse<T>>(path, data, SILENT_REQ)
      const body = unwrap(res)
      if (body) return body
    } catch (err) {
      lastErr = err
    }
  }
  throw lastErr ?? new Error('request failed')
}

/** 结果/报告接口：优先 v1（生产 8089），再试无 v1 */
async function getSimulationResultApi(simulationId: string) {
  const body = await getSimPaths<unknown>(
    simPaths(`/${simulationId}/result`),
    { params: { aggregation: 'raw' } },
  )
  return {
    ...body,
    data: normalizeSimulationResultData(body.data),
  }
}

async function postSimulationReportApi(
  simulationId: string,
  body: { report_type: string; format: string; include_charts: boolean },
) {
  return postSimPaths<BackendReportTask>(simPaths(`/${simulationId}/report`), body)
}

function toStartBody(payload: SimulationStartPayload, replaceRunning = true) {
  const level = Number(payload.initialLevel)
  const inflow = Number(payload.inflowRate)
  const opening = Math.round(Number(payload.gateOpening ?? 45))
  return {
    scenario_id: payload.scenarioId ?? DEFAULT_SCENARIO_ID,
    model_id: payload.modelId ?? DEFAULT_MODEL_ID,
    reservoir_id: payload.reservoirId ?? DEFAULT_RESERVOIR_ID,
    duration: Math.max(60, Math.round((payload.durationMin ?? 60) * 60)),
    speed: Math.min(10, Math.max(0.1, payload.speed ?? 1)),
    ...(replaceRunning
      ? { force: true, force_restart: true, replace_running: true, cancel_running: true }
      : {}),
    params: {
      initial_water_level: Number.isFinite(level) ? level : 380,
      inflow_rate: Number.isFinite(inflow) ? Math.round(inflow) : 1850,
      gate_opening: Math.max(0, Math.min(100, opening)),
    },
  }
}

/** 9.1 仿真场景列表 — GET /api/v1/simulation/scenarios */
export async function getSimulationScenarios(params?: {
  page?: number
  page_size?: number
  keyword?: string
}): Promise<ApiResponse<PageResult<SimulationScenarioItem>>> {
  const queryParams = { page: 1, page_size: 50, ...params }

  return withMockFallback(
    async () => {
      const res = await http.get<ApiResponse<PageResult<BackendScenarioItem>>>(
        `${SIM_V1_BASE}/scenarios`,
        { params: queryParams, ...SILENT_REQ },
      )
      const body = unwrap(res)
      if (!body?.data) throw new Error('scenarios failed')
      const list = (body.data.list ?? []).map(mapBackendScenario)
      return { ...body, data: { list, total: body.data.total ?? list.length } }
    },
    async () => ({
      code: 0,
      msg: 'ok',
      success: true,
      trace_id: 'mock-scenarios',
      data: {
        list: [
          {
            id: 1,
            name: '正常工况',
            type: 'production',
            status: 'active',
            model_id: DEFAULT_MODEL_ID,
          },
        ],
        total: 1,
      },
    }),
  )
}

/** 创建仿真场景（不走 Mock 假成功） */
export async function createSimulationScenario(
  payload: SimulationScenarioPayload,
): Promise<ApiResponse<SimulationScenarioItem>> {
  const res = await http.post<ApiResponse<BackendScenarioItem>>(
    `${SIM_V1_BASE}/scenarios`,
    payload,
  )
  const body = unwrap(res)
  if (!body?.data) throw new Error('创建场景失败')
  return { ...body, data: mapBackendScenario(body.data) }
}

/** 更新仿真场景（直连后端） */
export async function updateSimulationScenario(
  id: number,
  payload: Partial<SimulationScenarioPayload>,
): Promise<ApiResponse<SimulationScenarioItem>> {
  const res = await http.put<ApiResponse<BackendScenarioItem>>(
    `${SIM_V1_BASE}/scenarios/${id}`,
    payload,
  )
  const body = unwrap(res)
  if (!body?.data) throw new Error('更新场景失败')
  return { ...body, data: mapBackendScenario(body.data) }
}

/** 启动前将草稿/停用场景自动激活（后端 draft 状态不允许 start） */
export async function ensureScenarioActive(
  scenarioId: number,
  scenarios: SimulationScenarioItem[],
): Promise<SimulationScenarioItem | null> {
  const item = scenarios.find((s) => s.id === scenarioId)
  if (!item) return null
  if (item.status === 'active') return item
  const res = await updateSimulationScenario(scenarioId, { status: 'active' })
  return res.data
}

/** 删除仿真场景（不走 Mock 降级，避免假成功） */
export async function deleteSimulationScenario(id: number): Promise<ApiResponse<null>> {
  const res = await http.delete<ApiResponse<null>>(`${SIM_V1_BASE}/scenarios/${id}`)
  const body = unwrap(res)
  if (!body) throw new Error('删除场景失败')
  return body
}

/** 9.2 启动仿真 */
export async function startSimulation(
  params: SimulationStartPayload,
): Promise<ApiResponse<SimulationStartResult>> {
  return withMockFallback(
    async () => {
      const body = await postSimPaths<SimulationStartResult>(simPaths('/start'), toStartBody(params))
      const normalized =
        body.data?.simulation_id
          ? body.data
          : normalizeStartResult((body.data ?? {}) as Record<string, unknown>)
      if (!normalized?.simulation_id) throw new Error('start failed')
      return { ...body, data: normalized }
    },
    async () => {
      if (import.meta.env.DEV && !SIMULATION_USE_MOCK) {
        console.warn('[simulation] 后端 start 失败，请检查 scenario_id / model_id 是否有效')
      }
      await mockApi.startSimulation(params)
      const id = `MOCK-${Date.now()}`
      return {
        code: 0,
        msg: 'ok',
        success: true,
        trace_id: 'mock-sim-start',
        data: {
          simulation_id: id,
          status: 'running',
          ws_endpoint: '',
        },
      }
    },
  )
}

/** 9.4 获取仿真结果 */
export async function getSimulationResult(
  simulationId: string,
): Promise<ApiResponse<SimulationResultData>> {
  return getSimulationResultApi(simulationId)
}

export async function pauseSimulation(simulationId: string): Promise<ApiResponse<null>> {
  const id = encodeURIComponent(simulationId)
  return withMockFallback(
    async () => postSimPaths<null>(simPaths(`/${id}/pause`)),
    () => mockApi.pauseSimulation(),
  )
}

export async function resumeSimulation(simulationId: string): Promise<ApiResponse<null>> {
  const id = encodeURIComponent(simulationId)
  return withMockFallback(
    async () => postSimPaths<null>(simPaths(`/${id}/resume`)),
    () => mockApi.resumeSimulation(),
  )
}

export async function resetSimulation(simulationId: string): Promise<ApiResponse<null>> {
  const id = encodeURIComponent(simulationId)
  return withMockFallback(
    async () => postSimPaths<null>(simPaths(`/${id}/reset`)),
    () => mockApi.resetSimulation(),
  )
}

export async function getSimulationStatus(
  simulationId?: string,
): Promise<ApiResponse<SimulationRealtimeData>> {
  if (!simulationId) {
    if (SIMULATION_USE_MOCK) return mockApi.getSimulationStatus()
    throw new Error('缺少 simulation_id')
  }
  return withMockFallback(
    async () => {
      const body = await getSimPaths<unknown>(
        simPaths(`/${simulationId}/result`),
        { params: { aggregation: 'raw' } },
      )
      const resultData = normalizeSimulationResultData(body.data)
      return {
        ...body,
        data: mapResultDataToRealtime(resultData, simulationId),
      }
    },
    () => mockApi.getSimulationStatus(),
  )
}

export async function setSimulationGateOpening(
  simulationId: string,
  opening: number,
): Promise<ApiResponse<null>> {
  const id = encodeURIComponent(simulationId)
  return withMockFallback(
    async () =>
      putSimPaths<null>(simPaths(`/${id}/gate`), { gate_opening: Math.round(opening) }),
    () => mockApi.setGateOpening(opening),
  )
}

export async function getModelList(): Promise<ApiResponse<AiModel[]>> {
  return withMockFallback(
    async () => {
      const res = await getSettingsModels({ page: 1, page_size: 100 })
      if (res.data?.code === 0 && res.data?.data) {
        const list = res.data.data.list ?? []
        return {
          code: 0,
          msg: 'ok',
          success: true,
          trace_id: res.data.trace_id,
          data: list.map(modelInfoToAiModel),
        }
      }
      throw new Error('模型列表为空')
    },
    () => mockApi.getModelList(),
  )
}

/** 从 P2 模型版本管理拉取可选模型（GET /v1/settings/models） */
export async function getRegistryModelList(keyword?: string): Promise<ApiResponse<ModelInfo[]>> {
  return withMockFallback(
    async () => {
      const res = await getSettingsModels({
        page: 1,
        page_size: 100,
        keyword: keyword || undefined,
      })
      if (res.data?.code === 0 && res.data?.data) {
        return {
          code: 0,
          msg: 'ok',
          success: true,
          trace_id: res.data.trace_id,
          data: res.data.data.list ?? [],
        }
      }
      throw new Error('模型版本管理列表为空')
    },
    async () => {
      const mock = await mockApi.getModelList()
      return {
        code: 0,
        msg: 'ok',
        success: true,
        trace_id: 'mock-registry',
        data: (mock.data ?? []).map((m) => ({
          id: m.id,
          name: `${m.type} ${m.version}`,
          version: m.version,
          type: m.type.toLowerCase(),
          framework: 'pytorch',
          status: m.status === 'active' ? 'active' : 'ready',
          accuracy: m.metrics?.accuracy ?? 0,
          training_date: m.createdAt,
          size: 0,
          is_active: m.status === 'active' ? 1 : 0,
          deployed_nodes: 0,
          overall_score: m.metrics?.overallScore,
          health_grade: m.metrics?.healthGrade,
        })),
      }
    },
  )
}

/** 从模型版本管理导入：优先激活，失败则仍选用该 model_id 供仿真启动 */
export async function importModelFromRegistry(id: number): Promise<ApiResponse<AiModel>> {
  return withMockFallback(
    async () => {
      const listRes = await getSettingsModels({ page: 1, page_size: 100 })
      const row = listRes.data?.data?.list?.find((m) => m.id === id)
      if (!row) throw new Error('模型不存在，请刷新列表')

      rememberSimulationModel(id)
      const baseModel = modelInfoToAiModel(row)

      if (row.status === 'active' || row.is_active === 1) {
        return {
          code: 0,
          msg: '已选用当前激活模型',
          success: true,
          trace_id: listRes.data.trace_id ?? '',
          data: { ...baseModel, status: 'active' },
        }
      }

      let activateMsg = ''
      try {
        const res = await activateSettingsModel(id, {
          force: true,
          rollback_on_failure: true,
        })
        if (res.data?.code !== 0) {
          activateMsg = res.data?.msg || '激活失败'
        }
      } catch (err) {
        activateMsg = isApiBusinessError(err)
          ? err.message
          : err instanceof Error
            ? err.message
            : '激活失败'
      }

      let latest = row
      if (!activateMsg) {
        try {
          const again = await getSettingsModels({ page: 1, page_size: 100 })
          latest = again.data?.data?.list?.find((m) => m.id === id) ?? row
        } catch {
          latest = row
        }
        return {
          code: 0,
          msg: '已从模型版本管理导入并激活',
          success: true,
          trace_id: '',
          data: modelInfoToAiModel(latest),
        }
      }

      if (activateMsg && /模型文件不存在|model file|\.pth|file not found/i.test(activateMsg)) {
        sessionStorage.removeItem(SIM_SELECTED_MODEL_KEY)
        throw new Error(
          `该模型权重文件在服务器上不存在（${activateMsg}）。请在「${MODEL_REGISTRY_LABEL}」重新上传「${row.name}」，或选用状态为「激活中」的 Physics-Informed 模型。`,
        )
      }

      return {
        code: 0,
        msg: `已选用模型 #${id}（激活未成功：${activateMsg}，仿真仍将使用该模型）`,
        success: true,
        trace_id: '',
        data: modelInfoToAiModel(latest),
      }
    },
    async () => {
      await mockApi.activateModel(id)
      rememberSimulationModel(id)
      const mock = await mockApi.getModelList()
      const row = mock.data?.find((m) => m.id === id) ?? mock.data?.[0]
      if (!row) throw new Error('导入模型失败')
      return { code: 0, msg: 'ok', success: true, trace_id: 'mock-import', data: row }
    },
  )
}

export async function uploadModel(formData: FormData): Promise<ApiResponse<AiModel>> {
  return withMockFallback(
    async () => {
      const res = await uploadSettingsModel(formData)
      if (res.data?.code === 0 && res.data?.data) {
        return {
          code: 0,
          msg: 'ok',
          success: true,
          trace_id: res.data.trace_id,
          data: modelInfoToAiModel(res.data.data),
        }
      }
      throw new Error('上传模型失败')
    },
    () => mockApi.uploadModel(formData),
  )
}

export async function activateModel(id: number): Promise<ApiResponse<null>> {
  return withMockFallback(
    async () => {
      const res = await activateSettingsModel(id, { force: true, rollback_on_failure: true })
      if (res.data?.code === 0) {
        return { code: 0, msg: 'ok', success: true, trace_id: res.data.trace_id, data: null }
      }
      throw new Error('激活模型失败')
    },
    () => mockApi.activateModel(id),
  )
}

export async function startTraining(
  _config: TrainingConfig & { modelId: number },
): Promise<ApiResponse<{ taskId: string }>> {
  return withMockFallback(
    async () => {
      throw new Error('train not on api')
    },
    () => mockApi.startTraining(),
  )
}

export async function generateReport(
  simulationId: string | number,
  context?: SimulationReportContext,
): Promise<ApiResponse<SimulationReport>> {
  const id = String(simulationId)
  return withMockFallback(
    async () => {
      let reportTask: ApiResponse<BackendReportTask> | null = null
      try {
        reportTask = await postSimulationReportApi(id, {
          report_type: 'full',
          format: 'pdf',
          include_charts: true,
        })
      } catch {
        /* 后端 report 接口失败时仍可用 result + 本地状态生成 txt */
      }

      let resultData: SimulationResultData = { summary: {}, total: 0, points: [] }
      try {
        resultData = (await getSimulationResultApi(id)).data
      } catch {
        /* result 接口失败时完全使用页面当前仿真状态 */
      }

      const summary = resolveSimulationResultSummary(resultData, {
        simStatus: context?.simStatus,
        params: context?.params,
        gateOpening: context?.gateOpening,
      })

      const report = buildSimulationReport({
        simulationId: id,
        scene: context?.scene ?? 'normal',
        simParams: context?.params ?? {
          scene: 'normal',
          initialLevel: summary.max_upstream_level ?? 380,
          inflowRate: summary.max_inflow_rate ?? 1900,
          durationMin: 60,
        },
        resultSummary: summary,
        downloadUrl: reportTask?.data?.download_url ?? `local:${id}`,
        operatorName: context?.operatorName ?? '当前用户',
        reportId: reportTask?.data?.report_id,
      })

      saveStoredReport(report)
      saveReportBlob(report.id, buildLocalReportDocument(report, id))
      return {
        code: 0,
        msg: reportTask?.msg || '报告已生成',
        success: true,
        trace_id: reportTask?.trace_id ?? 'local-report',
        data: report,
      }
    },
    () => generateLocalReport(id, context),
  )
}

export async function getReportList(_params: {
  pageNum: number
  pageSize: number
  scene?: string
}): Promise<ApiResponse<PageResult<SimulationReport>>> {
  const stored = loadStoredReports().map(repairStoredReport)
  if (stored.length) {
    localStorage.setItem(REPORTS_STORAGE_KEY, JSON.stringify(stored.slice(0, 50)))
    return {
      code: 0,
      msg: 'ok',
      success: true,
      trace_id: 'local-reports',
      data: { list: stored, total: stored.length },
    }
  }
  return withMockFallback(
    async () => {
      throw new Error('report list api unavailable')
    },
    () => mockApi.getReportList(),
  )
}

export async function downloadReport(id: number): Promise<Blob> {
  const localContent = loadReportBlobs()[String(id)]
  if (localContent) {
    return new Blob([localContent], { type: 'text/plain;charset=utf-8' })
  }

  const report = loadStoredReports().find((r) => r.id === id)
  const simId = report
    ? extractSimulationIdFromContent(report.content) ?? String(report.runId)
    : String(id)

  if (!report?.filePath || report.filePath.startsWith('local:')) {
    if (report) {
      const repaired = repairStoredReport(report)
      return new Blob([buildLocalReportDocument(repaired, simId)], {
        type: 'text/plain;charset=utf-8',
      })
    }
    throw new Error('报告暂无下载内容，请重新生成')
  }

  const token = localStorage.getItem('token')
  const url = report.filePath.startsWith('http')
    ? report.filePath
    : `${import.meta.env.VITE_API_BASE_URL?.replace(/\/api$/, '') ?? ''}${report.filePath}`

  try {
    const res = await fetch(url, {
      headers: token ? { Authorization: `Bearer ${token}` } : {},
    })
    if (!res.ok) throw new Error(`下载失败 (${res.status})`)
    return await res.blob()
  } catch {
    return new Blob([buildLocalReportDocument(report, String(report.runId))], {
      type: 'text/plain;charset=utf-8',
    })
  }
}

export async function getFaultReviewList(params: {
  pageNum: number
  pageSize: number
  type?: string
  startTime?: string
  endTime?: string
}): Promise<ApiResponse<PageResult<FaultReview>>> {
  return withMockFallback(
    async () => {
      const res = await http.get<ApiResponse<PageResult<BackendIncidentItem>>>(INCIDENTS_V1, {
        params: toBackendIncidentQuery(params),
        ...SILENT_REQ,
      })
      const body = unwrap(res)
      if (!body?.data) throw new Error('incidents failed')
      const list = normalizeIncidentList(body.data.list ?? [])
      cacheIncidents(list)
      return {
        ...body,
        data: {
          list: list.map(mapBackendIncident),
          total: body.data.total ?? list.length,
        },
      }
    },
    () => mockApi.getFaultReviewList(params),
  )
}

export async function getFaultReviewDetail(id: number): Promise<ApiResponse<FaultReview>> {
  return withMockFallback(
    async () => {
      try {
        const res = await http.get<ApiResponse<BackendIncidentItem>>(`${INCIDENTS_V1}/${id}`, SILENT_REQ)
        const body = unwrap(res)
        if (body?.data) {
          cacheIncidents([body.data])
          return { ...body, data: mapBackendIncident(body.data) }
        }
      } catch {
        /* 尝试列表缓存 */
      }
      const raw = await fetchIncidentById(id)
      if (!raw) throw new Error('故障记录不存在')
      return { code: 0, msg: 'ok', success: true, trace_id: '', data: mapBackendIncident(raw) }
    },
    () => mockApi.getFaultReviewDetail(id),
  )
}

export async function submitFaultConclusion(
  id: number,
  conclusion: FaultConclusion,
): Promise<ApiResponse<null>> {
  return withMockFallback(
    async () => {
      throw new Error('fault conclusion not on api')
    },
    () => mockApi.submitFaultConclusion(id, conclusion),
  )
}

export async function importToSimulation(
  id: number,
): Promise<ApiResponse<SimulationParams & { gateOpening?: number }>> {
  return withMockFallback(
    async () => {
      const incident =
        incidentCache.get(id) ??
        (await fetchIncidentById(id))
      if (!incident) {
        throw new Error('故障记录不存在，请先在「历史故障复盘」刷新列表')
      }

      // 后端 10001：故障名称不能为空 — 需 incident_id + incident_name（及文档必填字段）
      const body = await postImportIncident(
        toBackendImportIncidentBody(incident) as Record<string, unknown>,
      )
      const incidentId = body.data?.incident_id ?? id
      const resolved =
        incidentCache.get(incidentId) ??
        incident

      return {
        ...body,
        msg: [
          body.msg || '故障复盘导入成功',
          body.data?.import_id ? `(${body.data.import_id})` : '',
        ].filter(Boolean).join(' '),
        data: mapImportIncidentResponse((body.data ?? {}) as Record<string, unknown>, resolved),
      }
    },
    () => mockApi.importToSimulation(id),
  )
}

export async function getPhysicsGuardSummary(): Promise<ApiResponse<PhysicsGuardSummary>> {
  return withMockFallback(
    async () => {
      const res = await http.get<ApiResponse<PhysicsGuardSummary>>(
        `${import.meta.env.VITE_API_V1_PREFIX ?? '/v1'}/admin/physics-guard`,
        { params: { reservoir_id: DEFAULT_RESERVOIR_ID }, ...SILENT_REQ },
      )
      const body = unwrap(res)
      if (!body?.data) throw new Error('physics guard failed')
      return body
    },
    () => mockApi.getPhysicsGuardSummary(),
  )
}

/** 按前端场景名匹配后端 scenario_id；custom 时优先使用场景库选中项 */
export function resolveScenarioId(
  scene: string,
  scenarios: SimulationScenarioItem[],
  selectedScenarioId?: number | null,
  models: AiModel[] = [],
): { scenarioId: number; modelId: number } {
  const pool = runnableScenarios(scenarios)

  if (scene === 'custom' && selectedScenarioId != null) {
    const picked =
      pool.find((s) => s.id === selectedScenarioId) ??
      scenarios.find((s) => s.id === selectedScenarioId)
    if (picked) {
      return {
        scenarioId: picked.id,
        modelId: picked.model_id ?? pickActiveModelId(models),
      }
    }
  }

  const keywords: Record<string, string[]> = {
    normal: ['正常', '工况'],
    flood: ['洪水', '汛', '入库'],
    dry: ['枯水', '淡水'],
    rainstorm: ['暴雨'],
  }
  const typeMap: Record<string, string> = {
    normal: 'production',
    flood: 'production',
    dry: 'energy',
    rainstorm: 'production',
  }
  const keys = keywords[scene] ?? []
  const matched = pool.find(
    (s) =>
      s.type === typeMap[scene] ||
      keys.some((k) => s.name.includes(k) || (s.description ?? '').includes(k)),
  )
  const pick = matched ?? pool[0] ?? scenarios[0]
  return {
    scenarioId: pick?.id ?? DEFAULT_SCENARIO_ID,
    modelId: pick?.model_id ?? pickActiveModelId(models),
  }
}

/** 将 9.4 结果点写入实时状态（仿真结束后） */
export function applyResultToRealtime(
  points: SimulationResultData['points'],
  durationSec: number,
): SimulationRealtimeData {
  if (!points.length) {
    return {
      status: 'finished',
      elapsedSec: durationSec,
      currentLevel: 0,
      currentDownstreamLevel: 0,
      currentFlow: 0,
      currentOpening: 0,
      historyLevels: [],
      historyFlows: [],
    }
  }
  const last = points[points.length - 1].values
  const historyLevels = points.map((p, i) => ({
    time: Math.round((i / Math.max(points.length - 1, 1)) * durationSec),
    value: p.values.upstream_level ?? 0,
  }))
  const historyFlows = points.map((p, i) => ({
    time: Math.round((i / Math.max(points.length - 1, 1)) * durationSec),
    value: p.values.inflow_rate ?? 0,
  }))
  return {
    status: 'finished',
    elapsedSec: durationSec,
    currentLevel: last.upstream_level ?? 0,
    currentDownstreamLevel: last.downstream_level ?? 0,
    currentFlow: last.inflow_rate ?? 0,
    currentOpening: Math.round(last.gate_opening ?? 0),
    historyLevels,
    historyFlows,
  }
}

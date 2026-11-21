/** 业务错误 — 保留 code / data 供登录页等场景解析 */
export class ApiBusinessError extends Error {
  code: number
  data: unknown

  constructor(code: number, msg: string, data?: unknown) {
    super(msg)
    this.name = 'ApiBusinessError'
    this.code = code
    this.data = data ?? null
  }
}

export function isApiBusinessError(err: unknown): err is ApiBusinessError {
  return err instanceof ApiBusinessError
}

/** 后端认证类业务码（20001–20008，如「未登录」「Token 过期」） */
export const AUTH_ERROR_CODE_MIN = 20001
export const AUTH_ERROR_CODE_MAX = 20008

export function isAuthBusinessCode(code: number): boolean {
  return code >= AUTH_ERROR_CODE_MIN && code <= AUTH_ERROR_CODE_MAX
}

export function isAuthError(err: unknown): boolean {
  if (isApiBusinessError(err)) return isAuthBusinessCode(err.code)
  if (err && typeof err === 'object' && 'response' in err) {
    const res = (err as { response?: { status?: number; data?: { code?: number } } }).response
    if (res?.status === 401) return true
    const code = res?.data?.code
    return code != null && isAuthBusinessCode(Number(code))
  }
  return false
}

/** HTTP 404 或业务码 30001「接口不存在」 */
export function isApiNotFoundError(err: unknown): boolean {
  if (isApiBusinessError(err)) {
    return err.code === 30001 || /接口不存在|not found/i.test(err.message)
  }
  if (err && typeof err === 'object' && 'response' in err) {
    return (err as { response?: { status?: number } }).response?.status === 404
  }
  return false
}

/** 仿真启动冲突：该场景已有运行中任务（§9.5 错误码 40002） */
export const SIMULATION_ALREADY_RUNNING_CODE = 40002

export function isSimulationAlreadyRunningError(err: unknown): boolean {
  return isApiBusinessError(err) && err.code === SIMULATION_ALREADY_RUNNING_CODE
}

/** 草稿场景不可启动（§9.5 错误码 30004） */
export const SIMULATION_DRAFT_SCENARIO_CODE = 30004

export function isSimulationDraftScenarioError(err: unknown): boolean {
  if (!isApiBusinessError(err)) return false
  return (
    err.code === SIMULATION_DRAFT_SCENARIO_CODE ||
    /草稿.*不允许|draft.*不允许|场景状态为\s*draft/i.test(err.message)
  )
}

const SIM_TASK_ID_PATTERN =
  /(?:SIM|sim|TASK|task)[-_]?[\w-]{4,}|task_no[:\s]+([\w-]+)|simulation_id[:\s]+([\w-]+)/i

/** 从文本中解析仿真任务编号（task_no / SIM-xxx 等） */
export function parseSimulationIdFromText(text: string): string | null {
  const trimmed = text.trim()
  if (!trimmed) return null
  const match = trimmed.match(SIM_TASK_ID_PATTERN)
  if (!match) return null
  return (match[1] ?? match[2] ?? match[0]).trim() || null
}

function pickSimulationId(row: Record<string, unknown>): string | null {
  const nested = row.running_task ?? row.active_task ?? row.current_task
  if (nested && typeof nested === 'object') {
    const fromNested = pickSimulationId(nested as Record<string, unknown>)
    if (fromNested) return fromNested
  }
  const id =
    row.simulation_id ??
    row.simulationId ??
    row.task_no ??
    row.task_id ??
    row.taskNo ??
    row.running_task_id ??
    row.active_task_id
  return id != null && String(id).length > 0 ? String(id) : null
}

/** 从 40002 等业务错误的 data / msg 中提取已有任务 ID */
export function extractSimulationIdFromError(err: unknown): string | null {
  if (!isApiBusinessError(err)) return null
  if (err.data && typeof err.data === 'object') {
    const fromData = pickSimulationId(err.data as Record<string, unknown>)
    if (fromData) return fromData
  }
  return parseSimulationIdFromText(err.message)
}

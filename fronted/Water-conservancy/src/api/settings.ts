// ============================================================
// 系统设置 API
// 对接后端 §8 系统设置模块 + §12 物理配置（总接口文档 v2.2）
// ============================================================
import http from './request'
import type {
  ApiResponse,
  PageResult,
  ThresholdRule,
  ThresholdUpdateParams,
  WeightConfig,
  WeightUpdateParams,
  ModelInfo,
  ModelActivateParams,
  ModelRollbackParams,
  ModelDeployParams,
  SystemUser,
  CreateUserParams,
  UpdateUserParams,
  ResetPasswordParams,
  LockUserParams,
  UnlockUserParams,
} from '@/shared/types'
import type { WeightHistoryItem } from '@/stores/settings'
import type {
  HealthOverview,
  TrendPoint,
  MetricsDetailItem,
  CompareResult,
} from '@/stores/aiHealth'
import type { InterlockRule } from '@/stores/gateInterlock'
import type { AIHealthOverviewResponse } from '@/types/gateai'
import type { ApiInterlockRule, ApiInterlockStat } from './interlockAdapter'
import type { BackendPhysicsGuardRaw } from './physicsGuardAdapter'

/** v1 路径前缀，由 .env 中 VITE_API_V1_PREFIX 控制 */
const V1 = import.meta.env.VITE_API_V1_PREFIX ?? '/v1'

/** 模型研判（Apifox「模型研判」）在 /api/settings/ai/*，不走 /v1 前缀 */
const AI = '/settings/ai'

// ════════════════════════════════════════════════════════════
// Tab1: 告警阈值 §8.1
// ════════════════════════════════════════════════════════════

const DEFAULT_THRESHOLD_RESERVOIR_ID = 1

function normalizeThresholdRule(raw: Record<string, unknown>): ThresholdRule {
  return {
    id: Number(raw.id),
    reservoir_id: raw.reservoir_id == null ? null : Number(raw.reservoir_id),
    metric: String(raw.metric ?? ''),
    warning_upper: Number(raw.warning_upper),
    warning_lower: Number(raw.warning_lower),
    critical_upper: Number(raw.critical_upper),
    critical_lower: Number(raw.critical_lower),
    debounce_seconds: Number(raw.debounce_seconds),
    enabled: Number(raw.enabled),
  }
}

/** 后端可能返回字符串数值；同 metric 多条时优先当前水库 */
export function normalizeThresholdList(
  list: unknown[],
  reservoirId = DEFAULT_THRESHOLD_RESERVOIR_ID,
): ThresholdRule[] {
  const normalized = list.map((item) =>
    normalizeThresholdRule(item as Record<string, unknown>),
  )
  const preferred = normalized.filter((r) => r.reservoir_id === reservoirId)
  const source = preferred.length ? preferred : normalized
  const map = new Map<string, ThresholdRule>()
  for (const item of source) {
    const existing = map.get(item.metric)
    if (!existing || (item.reservoir_id != null && existing.reservoir_id == null)) {
      map.set(item.metric, item)
    }
  }
  return [...map.values()].sort((a, b) => a.id - b.id)
}

export function toThresholdUpdatePayload(edit: ThresholdRule): ThresholdUpdateParams {
  return {
    warning_upper: Number(edit.warning_upper),
    warning_lower: Number(edit.warning_lower),
    critical_upper: Number(edit.critical_upper),
    critical_lower: Number(edit.critical_lower),
    debounce_seconds: Number(edit.debounce_seconds),
    enabled: Number(edit.enabled),
  }
}

export async function fetchThresholdList(reservoirId = DEFAULT_THRESHOLD_RESERVOIR_ID) {
  const res = await getThresholds({ reservoir_id: reservoirId })
  if (res.data?.code !== 0 || !Array.isArray(res.data.data)) {
    throw new Error(res.data?.msg || '获取阈值失败')
  }
  return normalizeThresholdList(res.data.data, reservoirId)
}

export function getThresholds(params?: { reservoir_id?: number; metric?: string }) {
  return http.get<ApiResponse<ThresholdRule[]>>(`${V1}/settings/thresholds`, { params })
}

export function updateThreshold(id: number, data: ThresholdUpdateParams) {
  return http.put<ApiResponse<null>>(`${V1}/settings/thresholds/${id}`, data)
}

// ════════════════════════════════════════════════════════════
// Tab2: 多目标权重 §8.2
// ════════════════════════════════════════════════════════════

export function getWeights() {
  return http.get<ApiResponse<WeightConfig>>(`${V1}/settings/weights`)
}

export function updateWeights(data: WeightUpdateParams) {
  return http.put<ApiResponse<null>>(`${V1}/settings/weights`, data)
}

export function getWeightHistory(params?: { page?: number; page_size?: number }) {
  return http.get<ApiResponse<WeightHistoryItem[]>>(`${V1}/settings/weights/history`, { params })
}

// ════════════════════════════════════════════════════════════
// Tab3: AI 模型管理 §8.3
// ════════════════════════════════════════════════════════════

export function getModels(params?: {
  page?: number
  page_size?: number
  type?: string
  status?: string
  keyword?: string
}) {
  return http.get<ApiResponse<PageResult<ModelInfo>>>(`${V1}/settings/models`, { params })
}

export function uploadModel(formData: FormData) {
  return http.post<ApiResponse<ModelInfo>>(`${V1}/settings/models/upload`, formData)
}

export function activateModel(id: number, data?: ModelActivateParams) {
  return http.post<ApiResponse<null>>(`${V1}/settings/models/${id}/activate`, data)
}

export function rollbackModel(id: number, data?: ModelRollbackParams) {
  return http.post<ApiResponse<null>>(`${V1}/settings/models/${id}/rollback`, data)
}

export function deleteModel(id: number) {
  return http.delete<ApiResponse<null>>(`${V1}/settings/models/${id}`)
}

export function deployModel(id: number, data: ModelDeployParams) {
  return http.post<ApiResponse<null>>(`${V1}/settings/models/${id}/deploy`, data)
}

export function getModelDetail(id: number) {
  return http.get<ApiResponse<ModelInfo>>(`${V1}/settings/models/${id}`)
}

// ════════════════════════════════════════════════════════════
// Tab4: AI 模型健康度（前端扩展，后端文档暂未定义）
// ════════════════════════════════════════════════════════════

export function getAIMetrics(params: { reservoir_id: number }) {
  return http.get<ApiResponse<HealthOverview>>(`${AI}/metrics`, { params })
}

export function getAIMetricsHistory(params: { reservoir_id: number; hours?: number }) {
  return http.get<ApiResponse<AIMetricsHistoryItem[]>>(`${AI}/metrics/history`, { params })
}

/** 历史趋势单条记录（与后端字段一致） */
export interface AIMetricsHistoryItem {
  metric_time: string
  prediction_score: number
  decision_score: number
  compliance_score: number
  overall_score: number
  health_grade: string
  water_level_mae_24h?: number
  physics_correction_rate?: number
  safety_override_rate?: number
  avg_physics_violation?: number
}

export function getAIHealthOverview() {
  return http.get<ApiResponse<AIHealthOverviewResponse>>(`${AI}/health`)
}

/** 模型指标明细列表（Apifox: GET /settings/ai/metrics/list） */
export function getAIMetricsDetail(params: {
  reservoir_id: number
  page?: number
  page_size?: number
  model_a_id?: number
  model_b_id?: number
}) {
  return http.get<ApiResponse<PageResult<MetricsDetailItem> | MetricsDetailItem[]>>(
    `${AI}/metrics/list`,
    { params },
  )
}

export function getAIVersionCompare(params: {
  reservoir_id: number
  model_a_id: number
  model_b_id: number
}) {
  return http.post<ApiResponse<CompareResult>>(`${AI}/metrics/compare`, params)
}

// ════════════════════════════════════════════════════════════
// Tab5: 物理防护配置（Apifox §12.5–12.9 → /api/v1/admin/physics-guard）
// ════════════════════════════════════════════════════════════

const ADMIN_PHYSICS_GUARD = '/v1/admin/physics-guard'

export function getPhysicsGuard(params: { reservoir_id: number }) {
  return http.get<ApiResponse<BackendPhysicsGuardRaw>>(ADMIN_PHYSICS_GUARD, { params })
}

export function updatePhysicsGuard(
  id: number,
  data: Record<string, unknown>,
  reservoirId?: number,
) {
  return http.put<ApiResponse<BackendPhysicsGuardRaw>>(`${ADMIN_PHYSICS_GUARD}/${id}`, data, {
    params: reservoirId != null ? { reservoir_id: reservoirId } : undefined,
  })
}

export function getPhysicsGuardHistory(params: { reservoir_id: number }) {
  return http.get<ApiResponse<BackendPhysicsGuardRaw[]>>(`${ADMIN_PHYSICS_GUARD}/history`, {
    params,
  })
}

export function rollbackPhysicsGuard(id: number) {
  return http.post<ApiResponse<BackendPhysicsGuardRaw>>(
    `${ADMIN_PHYSICS_GUARD}/${id}/rollback`,
  )
}

export function clonePhysicsGuard(data: {
  source_reservoir_id: number
  target_reservoir_id: number
}) {
  return http.post<ApiResponse<BackendPhysicsGuardRaw>>(`${ADMIN_PHYSICS_GUARD}/clone`, {
    from_reservoir_id: data.source_reservoir_id,
    to_reservoir_id: data.target_reservoir_id,
  })
}

// ════════════════════════════════════════════════════════════
// Tab6: 阀门互锁规则（前端扩展，后端文档暂未定义）
// ════════════════════════════════════════════════════════════

export function getInterlockRules(params: { reservoir_id: number }) {
  return http.get<ApiResponse<InterlockRule[]>>(`${V1}/settings/gate-interlock/rules`, { params })
}

export function updateInterlockRule(id: number, data: Record<string, unknown>) {
  return http.put<ApiResponse<ApiInterlockRule>>(`${V1}/settings/gate-interlock/rules/${id}`, data)
}

export function toggleInterlockRule(id: number, enabled: boolean) {
  return http.post<ApiResponse<ApiInterlockRule>>(
    `${V1}/settings/gate-interlock/rules/${id}/toggle`,
    { enabled },
  )
}

export function getInterlockLogs(params: {
  reservoir_id: number
  page?: number
  page_size?: number
  rule_ids?: number[]
  start_time?: string
  end_time?: string
}) {
  return http.get<ApiResponse<PageResult<import('@/types/gateai').GateInterlockLogApiItem>>>(
    `${V1}/settings/gate-interlock/logs`,
    { params },
  )
}

export function getInterlockStats(params: { reservoir_id: number; days?: number }) {
  return http.get<ApiResponse<ApiInterlockStat[]>>(`${V1}/settings/gate-interlock/stats`, { params })
}

// ════════════════════════════════════════════════════════════
// Tab7: 用户管理 §8.4
// ════════════════════════════════════════════════════════════

export function getUsers(params?: {
  page?: number
  page_size?: number
  role_id?: number
  is_enabled?: number
  keyword?: string
}) {
  return http.get<ApiResponse<PageResult<SystemUser>>>(`${V1}/settings/users`, { params })
}

export function createUser(data: CreateUserParams) {
  return http.post<ApiResponse<null>>(`${V1}/settings/users`, data)
}

export function updateUser(id: number, data: UpdateUserParams) {
  return http.put<ApiResponse<null>>(`${V1}/settings/users/${id}`, data)
}

export function resetUserPassword(id: number, data?: ResetPasswordParams) {
  return http.post<ApiResponse<null>>(`${V1}/settings/users/${id}/reset-password`, data)
}

export function lockUser(id: number, data: LockUserParams) {
  return http.post<ApiResponse<null>>(`${V1}/settings/users/${id}/lock`, data)
}

export function unlockUser(id: number, data?: UnlockUserParams) {
  return http.post<ApiResponse<null>>(`${V1}/settings/users/${id}/unlock`, data)
}

export function deleteUser(id: number) {
  return http.delete<ApiResponse<null>>(`${V1}/settings/users/${id}`)
}

// ════════════════════════════════════════════════════════════
// Tab8: 角色页面权限 §8.5
// ════════════════════════════════════════════════════════════

/** 后端返回的页面条目 */
export interface BackendRolePage {
  pageId: string
  pageName: string
  module: string
  path: string | null
  authorizedRoleNames: string[] // 中文角色名
}

/** GET 响应 data */
export interface RolePagePermissionsData {
  allRoles: string[] // 中文角色名
  pages: BackendRolePage[]
}

/** 获取角色页面权限配置 */
export function getRolePagePermissions() {
  return http.get<ApiResponse<RolePagePermissionsData>>(`${V1}/settings/role-permissions`)
}

/** 保存角色页面权限配置的请求格式 */
export interface SaveRolePagePermissionItem {
  pageId: string
  roleNames: string[] // 中文角色名
}

/** 保存角色页面权限配置（批量更新） */
export function saveRolePagePermissions(data: { permissions: SaveRolePagePermissionItem[] }) {
  return http.post<ApiResponse<null>>(`${V1}/settings/role-permissions/save`, data)
}

/** 重置角色页面权限为默认配置 */
export function resetRolePagePermissions() {
  return http.post<ApiResponse<RolePagePermissionsData>>(`${V1}/settings/role-permissions/reset`)
}

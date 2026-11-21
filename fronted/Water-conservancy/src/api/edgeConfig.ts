// ============================================================
// 边缘端配置下发 — API 接口层
// 依据：《总接口文档》§12 物理配置 + §8.3.6 模型下发 + 配置同步
// 包含：物理参数拉取、物理防护配置、模型下发、配置同步状态
// ============================================================
import http from './request'
import type { ApiResponse, PageResult } from '@/shared/types'
import type {
  EdgePhysicsConfig,
  EdgeSyncStatus,
  ModelDeployParams,
  ModelDeployResult,
} from '@/types/edgeNode'
import type { PhysicsGuardSummary } from '@/types/dispatch'
import type { BackendPhysicsGuardRaw } from './physicsGuardAdapter'

const V1_PREFIX = import.meta.env.VITE_API_V1_PREFIX ?? '/v1'
/** Apifox §12.1：/api/edge/physics-config（无 v1 前缀） */
const EDGE_LEGACY_BASE = '/edge'

// ════════════════════════════════════════════════════════════
// §12 物理配置接口
// ════════════════════════════════════════════════════════════

// ── §12.1 边缘端拉取物理参数 ──
// 边缘端启动时/定时拉取（建议30分钟），使用 EdgeToken 认证
export function fetchEdgePhysicsConfig(reservoirId: number) {
  return http.get<ApiResponse<EdgePhysicsConfig>>(
    `${EDGE_LEGACY_BASE}/physics-config/${reservoirId}`,
  )
}

// ── §12.2 物理参数管理列表（后台）──
export function getPhysicalParameters(params: {
  reservoir_id: number
  page?: number
  page_size?: number
}) {
  return http.get<ApiResponse<PageResult<Record<string, unknown>>>>(
    '/admin/physical-parameters',
    { params },
  )
}

// ── §12.3 新增/更新物理参数（后台）──
export function savePhysicalParameter(data: {
  reservoir_id: number
  water_level: number
  surface_area: number
  max_discharge?: number
  remark?: string
}) {
  return http.post<ApiResponse<null>>('/admin/physical-parameters', data)
}

// ── §12.4 删除物理参数（后台）──
export function deletePhysicalParameter(id: number) {
  return http.delete<ApiResponse<null>>(`/admin/physical-parameters/${id}`)
}

// ════════════════════════════════════════════════════════════
// §12.5-12.9 物理防护配置（后台管理）
// ════════════════════════════════════════════════════════════

// ── §12.5 获取物理防护配置 ──
export function getPhysicsGuard(reservoirId?: number) {
  return http.get<ApiResponse<PhysicsGuardSummary>>(
    `${V1_PREFIX}/admin/physics-guard`,
    { params: { reservoir_id: reservoirId ?? 1 } },
  )
}

// ── §12.6 更新物理防护配置 ──
export function updatePhysicsGuard(id: number, data: Record<string, unknown>) {
  return http.put<ApiResponse<null>>(`${V1_PREFIX}/admin/physics-guard/${id}`, data)
}

// ── §12.7 物理防护配置变更历史 ──
export function getPhysicsGuardHistory(reservoirId?: number) {
  return http.get<
    ApiResponse<PageResult<{ id: number; config_version: string; changed_at: string }>>
  >(`${V1_PREFIX}/admin/physics-guard/history`, {
    params: { reservoir_id: reservoirId ?? 1 },
  })
}

// ── §12.8 回滚物理防护配置 ──
export function rollbackPhysicsGuard(id: number) {
  return http.post<ApiResponse<null>>(`${V1_PREFIX}/admin/physics-guard/${id}/rollback`)
}

// ── §12.9 跨水库复制物理防护配置 ──
export function clonePhysicsGuard(fromReservoirId: number, toReservoirId: number) {
  return http.post<ApiResponse<BackendPhysicsGuardRaw>>(`${V1_PREFIX}/admin/physics-guard/clone`, {
    from_reservoir_id: fromReservoirId,
    to_reservoir_id: toReservoirId,
  })
}

// ════════════════════════════════════════════════════════════
// §8.3.6 模型下发至边缘端
// ════════════════════════════════════════════════════════════

// ── 下发模型至边缘端 ──
export function deployModelToEdge(modelId: number, data: ModelDeployParams) {
  return http.post<ApiResponse<ModelDeployResult>>(
    `/settings/models/${modelId}/deploy`,
    data,
  )
}

// ════════════════════════════════════════════════════════════
// 配置同步状态查询
// ════════════════════════════════════════════════════════════

// ── 查询单个边缘节点同步状态 ──
export function getEdgeSyncStatus(edgeNodeId: number) {
  return http.get<ApiResponse<EdgeSyncStatus>>(
    `${EDGE_LEGACY_BASE}/physics-config/${edgeNodeId}`,
    {
      // 复用物理配置接口，返回含版本信息
      params: { check_only: 1 },
    },
  )
}

// ── 批量查询所有边缘节点同步状态 ──
// 复用 §6.1 边缘节点列表接口，返回字段含 model_version / autonomy_mode 等同步信息
export function getEdgeSyncStatusBatch(reservoirId?: number) {
  return http.get<ApiResponse<PageResult<EdgeSyncStatus>>>(
    `${V1_PREFIX}/edge-nodes`,
    { params: { reservoir_id: reservoirId, page: 1, page_size: 100 } },
  )
}

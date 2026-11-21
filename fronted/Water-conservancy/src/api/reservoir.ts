// ============================================================
// 水库管理 — API 接口层
// 依据：《总接口文档》§5 水库管理模块（5 接口）
// ============================================================
import http from './request'
import type { ApiResponse, PageResult } from '@/shared/types'

const V1_PREFIX = import.meta.env.VITE_API_V1_PREFIX ?? '/v1'
const RESERVOIRS_BASE = `${V1_PREFIX}/reservoirs`

// ── 水库类型 ──
export interface ReservoirItem {
  id: number
  name: string
  code: string
  type: 'daily_regulation' | 'seasonal' | 'multi_year'
  normal_water_level: number
  installed_capacity: number
  status: 'active' | 'inactive' | 'maintenance'
}

export interface ReservoirDetail {
  id: number
  name: string
  code: string
  type: string
  dead_water_level: number
  normal_water_level: number
  flood_limit_level: number
  design_flood_level: number
  check_flood_level: number
  total_capacity: number
  installed_capacity: number
  ecological_flow: number
  location_lat?: number
  location_lng?: number
  status: string
  edge_node_count: number
  equipment_count: number
  created_at?: string
  updated_at?: string
}

export interface ReservoirCreateParams {
  name: string
  code: string
  type: 'daily_regulation' | 'seasonal' | 'multi_year'
  dead_water_level: number
  normal_water_level: number
  flood_limit_level: number
  design_flood_level: number
  check_flood_level: number
  total_capacity: number
  installed_capacity?: number
  ecological_flow: number
  location_lat?: number
  location_lng?: number
}

export type ReservoirUpdateParams = Partial<ReservoirCreateParams>

// ── §5.1 水库列表 ──
export function getReservoirs(params?: {
  page?: number
  page_size?: number
  status?: string
  keyword?: string
}) {
  return http.get<ApiResponse<PageResult<ReservoirItem>>>(RESERVOIRS_BASE, { params })
}

// ── §5.2 水库详情 ──
export function getReservoirDetail(id: number, _equipmentCount?: number) {
  return http.get<ApiResponse<ReservoirDetail>>(`${RESERVOIRS_BASE}/${id}`)
}

// ── 水库下拉列表（兼容旧调用）──
export async function fetchReservoirList() {
  try {
    const res = await getReservoirs({ page: 1, page_size: 100 })
    return (res.data?.data?.list ?? []).map((r) => ({ id: r.id, name: r.name }))
  } catch {
    return [
      { id: 1, name: '向家坝' },
      { id: 2, name: '溪洛渡' },
      { id: 3, name: '三峡' },
      { id: 4, name: '模拟水库' },
    ]
  }
}

// ── 水库物理防护摘要（兼容旧调用，来自 physics-guard）──
import type { PhysicsGuardSummary } from '@/types/dispatch'
import type { BackendPhysicsGuardRaw } from './physicsGuardAdapter'
import { mapBackendPhysicsGuardSummary } from './physicsGuardAdapter'

export async function fetchReservoirPhysicsSummary(reservoirId: number): Promise<PhysicsGuardSummary> {
  try {
    const res = await http.get<ApiResponse<BackendPhysicsGuardRaw>>(
      `${V1_PREFIX}/admin/physics-guard`,
      { params: { reservoir_id: reservoirId } },
    )
    if (res.data?.code === 0 && res.data.data) {
      return mapBackendPhysicsGuardSummary(res.data.data)
    }
  } catch { /* fallback */ }
  // mock fallback
  return {
    reservoir_id: reservoirId,
    reservoir_name: '',
    config_version: '1.0.0',
    is_active: true,
    upstream_emergency: 193,
    upstream_danger: 190,
    upstream_warning: 188,
    fusion_l3_confidence: 0.7,
    fusion_l3_risk: 0.3,
    last_sync_at: null,
    sync_status: 'synced',
  }
}

// ── §5.3 新增水库（role.admin）──
export function createReservoir(data: ReservoirCreateParams) {
  return http.post<ApiResponse<ReservoirDetail>>(RESERVOIRS_BASE, data)
}

// ── §5.4 更新水库（role.admin）──
export function updateReservoir(id: number, data: ReservoirUpdateParams) {
  return http.put<ApiResponse<ReservoirDetail>>(`${RESERVOIRS_BASE}/${id}`, data)
}

// ── §5.5 删除水库（软删除，role.admin）──
export function deleteReservoir(id: number) {
  return http.delete<ApiResponse<null>>(`${RESERVOIRS_BASE}/${id}`)
}

// ============================================================
// 设备管理 API
// 对接后端 §7 设备管理模块（总接口文档 v2.2）
// ============================================================
import http from './request'
import type {
  ApiResponse,
  PageResult,
  Equipment,
  EquipmentDetail,
  EquipmentRestartParams,
  EquipmentStatusParams,
  StatusChangeResult,
} from '@/shared/types'

// ── 全部设备列表（监控大屏 / 设备管理） ──
export interface EquipmentAllListItem {
  id: number
  name: string
  type: string
  status: string
  code?: string
  reservoir_id?: number
  reservoir_name?: string
  manufacturer?: string
  model?: string
  health_score?: number
  last_online?: string
  install_location?: string
  group?: string
}

/** 将 all-list 接口字段规范化为前端 Equipment 结构 */
export function normalizeEquipmentItem(
  raw: EquipmentAllListItem,
  fallbackReservoirId: number,
  fallbackReservoirName = '',
): Equipment {
  return {
    id: raw.id,
    name: raw.name,
    code: raw.code ?? `EQ-${String(raw.id).padStart(3, '0')}`,
    type: raw.type,
    reservoir_id: raw.reservoir_id ?? fallbackReservoirId,
    reservoir_name: raw.reservoir_name ?? fallbackReservoirName,
    status: raw.status,
    manufacturer: raw.manufacturer ?? '—',
    model: raw.model ?? '—',
    health_score: raw.health_score ?? 0,
    last_online: raw.last_online ?? '',
    install_location: raw.install_location,
    group: raw.group,
  }
}

// 全部设备列表（监控大屏复用 §2.1）
export function getEquipmentAllList(params: { reservoir_id: number }) {
  return http.get<ApiResponse<EquipmentAllListItem[]>>(`/equipment/all-list`, { params })
}

// 设备列表（分页）§7.1
export function getEquipmentList(params: {
  page?: number
  page_size?: number
  reservoir_id?: number
  type?: string
  status?: string
  keyword?: string
}) {
  return http.get<ApiResponse<PageResult<Equipment>>>('/equipment/list', { params })
}

// 设备详情 §7.2
export function getEquipmentDetail(id: number) {
  return http.get<ApiResponse<EquipmentDetail>>(`/equipment/${id}`)
}

// 远程重启 §7.3
export function restartEquipment(id: number, data: EquipmentRestartParams) {
  return http.post<ApiResponse<{ command_id: string; status: string }>>(`/equipment/${id}/restart`, data)
}

// 更新设备状态 §7.4
export function updateEquipmentStatus(id: number, data: EquipmentStatusParams) {
  return http.put<ApiResponse<StatusChangeResult>>(`/equipment/${id}/status`, data)
}

// 导出设备台账 §7.5
export function exportEquipment(params: {
  reservoir_id?: number
  type?: string
  status?: string
  format?: string
}) {
  return http.get(`/equipment/export`, { params, responseType: 'blob' })
}

// ════════════════════════════════════════════════════════════
// 以下为前端扩展接口（后端文档暂未定义，待后端补充）
// ════════════════════════════════════════════════════════════

export function updateEquipment(id: number, data: Record<string, unknown>) {
  return http.put<ApiResponse<null>>(`/equipment/${id}`, data)
}

export function markEquipmentFault(id: number, data: { description: string; fault_type?: string }) {
  return http.post<ApiResponse<null>>(`/equipment/${id}/mark-fault`, data)
}

export function markEquipmentNormal(id: number) {
  return http.post<ApiResponse<null>>(`/equipment/${id}/mark-normal`)
}

export function getEquipmentFaults(id: number, params?: { page?: number; page_size?: number }) {
  return http.get<ApiResponse<PageResult<Record<string, unknown>>>>(`/equipment/${id}/faults`, { params })
}

export function getEquipmentLogs(id: number, params?: { page?: number; page_size?: number; type?: string }) {
  return http.get<ApiResponse<PageResult<Record<string, unknown>>>>(`/equipment/${id}/logs`, { params })
}

export function getEquipmentGroups() {
  return http.get<ApiResponse<string[]>>(`/equipment/groups`)
}

// 查看边缘节点配置同步状态 §12.1
export function getEdgeSyncStatus(edgeNodeId: string) {
  return http.get<ApiResponse<{
    last_sync_time: string
    config_version: string
    sync_status: 'synced' | 'pending' | 'failed'
    config_hash: string
  }>>(`/edge/physics-config/${edgeNodeId}`)
}

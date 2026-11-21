// ============================================================
// 边缘节点管理 — API 接口层
// 依据：《总接口文档》§6 边缘节点管理模块（5 接口）
// ============================================================
import http from './request'
import type { ApiResponse, PageResult } from '@/shared/types'
import type {
  EdgeNodeItem,
  EdgeNodeDetail,
  EdgeNodeCreateParams,
  EdgeNodeHeartbeatParams,
} from '@/types/edgeNode'

const V1_PREFIX = import.meta.env.VITE_API_V1_PREFIX ?? '/v1'
const EDGE_NODES_BASE = `${V1_PREFIX}/edge-nodes`

// ── §6.1 边缘节点列表 ──
export function getEdgeNodes(params?: {
  page?: number
  page_size?: number
  reservoir_id?: number
  status?: string
}) {
  return http.get<ApiResponse<PageResult<EdgeNodeItem>>>(EDGE_NODES_BASE, { params })
}

// ── §6.2 边缘节点详情 ──
export function getEdgeNodeDetail(id: number) {
  return http.get<ApiResponse<EdgeNodeDetail>>(`${EDGE_NODES_BASE}/${id}`)
}

// ── §6.3 注册边缘节点 ──
export function createEdgeNode(data: EdgeNodeCreateParams) {
  return http.post<ApiResponse<EdgeNodeDetail>>(EDGE_NODES_BASE, data)
}

// ── §6.4 心跳上报 ──
export function sendHeartbeat(id: number, data: EdgeNodeHeartbeatParams) {
  return http.post<ApiResponse<null>>(`${EDGE_NODES_BASE}/${id}/heartbeat`, data)
}

// ── §6.5 删除边缘节点 ──
export function deleteEdgeNode(id: number) {
  return http.delete<ApiResponse<null>>(`${EDGE_NODES_BASE}/${id}`)
}

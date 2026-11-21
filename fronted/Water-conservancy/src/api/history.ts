// ============================================================
// 历史查询 — API（Mock 降级通过 HISTORY_MOCK_FALLBACK 控制）
// ============================================================
import http from './request'
import type { ApiResponse } from '@/shared/types'

export const HISTORY_MOCK_FALLBACK = false
import type { HistoryDataPoint } from '@/types/monitoring'

// 后端实际响应结构
interface HistoryApiResponse {
  start_time: string
  end_time: string
  interval: string
  total: number
  points: Array<{
    timestamp: string
    values: Record<string, string>
  }>
}

/** POST /v1/history/export — 提交异步导出任务 */
export function exportHistoryData(data: {
  equipment_ids?: number[]
  start_time: string
  end_time: string
  metrics: string[]
  format?: 'csv' | 'excel' | 'json'
  interval?: string
}) {
  return http.post<ApiResponse<{ task_id: string }>>('/v1/history/export', data)
}

/** GET /v1/history/export/{task_id}/status — 查询导出任务状态 */
export function getExportStatus(taskId: string) {
  return http.get<ApiResponse<{ status: string; download_url?: string }>>(
    `/v1/history/export/${taskId}/status`,
  )
}

export function getHistoryData(params: {
  reservoir_id: number
  start_time: string
  end_time: string
  metrics?: string
  interval?: string
}) {
  return http.get<ApiResponse<HistoryApiResponse>>('/v1/history/data', { params })
}

export const METRIC_MAP: Record<string, string> = {
  upstreamLevel: 'upstream_level', downstreamLevel: 'downstream_level',
  inflowRate: 'inflow_rate', outflowRate: 'outflow_rate',
  gateOpening: 'gate_opening', powerOutput: 'power_output',
  flowRate: 'inflow_rate',
}

const INTERVAL_MAP: Record<string, string> = {
  raw: '1m', '5min': '5m', hour: '1h', day: '1d',
}

function fmtDate(iso: string): string {
  const d = iso.replace('T', ' ').trim()
  return d.length === 16 ? d + ':00' : d.slice(0, 19)
}

export async function fetchHistoryData(params: {
  reservoirId: number; start: string; end: string; metrics: string[]; granularity: string
}): Promise<HistoryDataPoint[]> {
  const startTime = params.start
    ? fmtDate(params.start)
    : new Date(Date.now() - 7 * 86400000).toISOString().replace('T', ' ').slice(0, 19)
  const endTime = params.end
    ? fmtDate(params.end)
    : new Date().toISOString().replace('T', ' ').slice(0, 19)

  try {
    const res = await getHistoryData({
      reservoir_id: params.reservoirId,
      start_time: startTime,
      end_time: endTime,
      metrics: params.metrics.map((m) => METRIC_MAP[m] ?? m).join(','),
      interval: INTERVAL_MAP[params.granularity] ?? '5m',
    })
    if (res.data?.code === 0 && res.data.data?.points?.length) {
      return res.data.data.points.map((p) => ({
        time: new Date(p.timestamp).getTime(),
        label: new Date(p.timestamp).toLocaleString('zh-CN'),
        upstreamLevel: parseFloat(p.values.upstream_level ?? '0'),
        downstreamLevel: parseFloat(p.values.downstream_level ?? '0'),
        inflowRate: parseFloat(p.values.inflow_rate ?? '0'),
        outflowRate: parseFloat(p.values.outflow_rate ?? '0'),
        gateOpening: parseFloat(p.values.gate_opening ?? '0'),
        powerOutput: parseFloat(p.values.power_output ?? '0'),
        event: null,
      }))
    }
  } catch (e) { if (!HISTORY_MOCK_FALLBACK) throw e }
  return []
}

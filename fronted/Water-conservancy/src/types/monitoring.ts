// ============================================================
// 监控 & 历史查询 — 类型定义
// ============================================================

/** GET /monitoring/realtime 实时监测数据 */
export interface MonitoringRealtime {
  upstream_level: number
  downstream_level: number
  inflow_rate: number
  outflow_rate: number
  gate_opening: number
  power_output: number
  capacity: number
  timestamp: string
}

/** 前端使用的 KPI 快照（camelCase，由 L2 层映射） */
export interface RealtimeKpi {
  upstreamLevel: number
  downstreamLevel: number
  inflowRate: number
  outflowRate: number
  gateOpening: number
  powerOutput: number
  capacity: number
  timestamp: string
}

/** GET /v1/alarms 告警列表条目 */
export interface AlarmPageItem {
  id: number
  alarm_no?: string
  level: string
  type?: string
  message: string
  status?: string
  created_at: string
}

/** 前端使用的告警摘要 */
export interface DashboardAlarm {
  time: string
  level: 'warning' | 'critical'
  msg: string
  status: string
}

/** GET /v1/history/data 查询参数 */
export interface HistoryQueryParams {
  reservoir_id: number
  start: string
  end: string
  metrics: string // 逗号分隔
  granularity: 'raw' | '5min' | 'hour' | 'day'
}

/** GET /v1/history/data 原始数据点（后端 snake_case） */
export interface HistoryDataPointRaw {
  time: string
  upstream_level: number
  downstream_level: number
  inflow_rate: number
  outflow_rate: number
  gate_opening: number
  power_output: number
  event?: { type: string; label: string; color: string } | null
}

/** 前端使用的历史数据点（camelCase） */
export interface HistoryDataPoint {
  time: number
  label: string
  upstreamLevel: number
  downstreamLevel: number
  inflowRate: number
  outflowRate: number
  gateOpening: number
  powerOutput: number
  event: { type: string; label: string; color: string } | null
}
